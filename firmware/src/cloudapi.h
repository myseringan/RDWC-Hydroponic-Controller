// CloudAPI для WegaBox - отправка данных на ваш сервер
// С логированием и исправлением проверки pHmV

void cloudLog(String level, String message);  // forward declaration

void TaskWegaApi(void *parameters)
{
  http.setConnectTimeout(10000);
  http.setTimeout(10000);
  
  for (;;)
  {
    if (OtaStart == true)
      vTaskDelete(NULL);
      
    syslog_ng("CloudApi loop. WIFI RSSI=" + String(WiFi.RSSI()));

    // ==================== ОТПРАВКА ДАННЫХ НА СЕРВЕР ====================
    String httpstr = wegaapi;
    httpstr += "?action=data";
    httpstr += "&device_id=" + String(HOSTNAME);
    httpstr += "&api_key=" + wegaauth;
    httpstr += "&uptime=" + fFTS(millis() / 1000, 0);
    
    // Температура корней (DS18B20)
    if (RootTemp && !isnan(RootTemp))
      httpstr += "&RootTemp=" + fFTS(RootTemp, 3);
    
    // Температура воздуха
    if (AirTemp && !isnan(AirTemp))
      httpstr += "&AirTemp=" + fFTS(AirTemp, 3);
    
    // Влажность воздуха  
    if (AirHum && !isnan(AirHum))
      httpstr += "&AirHum=" + fFTS(AirHum, 3);
    
    // Давление воздуха
    if (AirPress && !isnan(AirPress))
      httpstr += "&AirPress=" + fFTS(AirPress, 3);
    
    // Датчик Холла
    if (hall && !isnan(hall))
      httpstr += "&hall=" + fFTS(hall, 3);
    
    // pH (RAW значения) - ИСПРАВЛЕНО: проверяем != 0 вместо просто truthy
    if (!isnan(pHmV))
      httpstr += "&pHmV=" + fFTS(pHmV, 4);
    if (!isnan(pHraw))
      httpstr += "&pHraw=" + fFTS(pHraw, 4);
    
    // CO2 и VOC
    if (CO2 && !isnan(CO2))
      httpstr += "&CO2=" + fFTS(CO2, 0);
    if (tVOC && !isnan(tVOC))
      httpstr += "&tVOC=" + fFTS(tVOC, 0);
    
    // NTC термистор (RAW)
    if (NTC && !isnan(NTC))
      httpstr += "&NTC=" + fFTS(NTC, 3);
    
    // EC электроды (RAW значения Ap и An)
    if (Ap && !isnan(Ap))
      httpstr += "&Ap=" + fFTS(Ap, 3);
    if (An && !isnan(An))
      httpstr += "&An=" + fFTS(An, 3);
    
    // Уровень раствора
    if (Dist && !isnan(Dist))
      httpstr += "&Dist=" + fFTS(Dist, 3);
    if (DstRAW && !isnan(DstRAW))
      httpstr += "&DstRAW=" + fFTS(DstRAW, 3);
    
    // Фоторезистор
    if (PR != -1 && !isnan(PR))
      httpstr += "&PR=" + fFTS(PR, 3);
    
    // Температура CPU
    if (CPUTemp && !isnan(CPUTemp))
      httpstr += "&CPUTemp=" + fFTS(CPUTemp, 3);

    // Вычисленные значения (если есть)
    if (wNTC && !isnan(wNTC) && !isinf(wNTC))
      httpstr += "&wNTC=" + fFTS(wNTC, 3);
    if (wR2 && !isnan(wR2) && !isinf(wR2))
      httpstr += "&wR2=" + fFTS(wR2, 3);
    if (wEC && !isnan(wEC) && !isinf(wEC))
      httpstr += "&wEC=" + fFTS(wEC, 3);
    if (wpH && !isnan(wpH) && !isinf(wpH))
      httpstr += "&wpH=" + fFTS(wpH, 3);
    
    // eRAW для дополнительных вычислений
    if (eRAW && !isnan(eRAW) && !isinf(eRAW))
      httpstr += "&eRAW=" + fFTS(eRAW, 3);
    
    // GPIO состояние (для помп)
    if (readGPIO)
      httpstr += "&readGPIO=" + String(readGPIO);
    if (PWD1)
      httpstr += "&PWD1=" + String(PWD1);
    if (PWD2)
      httpstr += "&PWD2=" + String(PWD2);
    if (ECStabOn)
      httpstr += "&ECStabOn=" + String(ECStabOn);

    // WiFi сигнал
    httpstr += "&RSSI=" + String(WiFi.RSSI());

    // Отправляем запрос
    http.begin(client, httpstr);
    http.GET();
    wegareply = http.getString();
    
    syslog_ng("Server reply: " + wegareply);

    // ==================== ПОЛУЧЕНИЕ ДАННЫХ ОТ СЕРВЕРА ====================
    DynamicJsonDocument doc(4096);
    DeserializationError error = deserializeJson(doc, wegareply);
    
    if (error)
    {
      err_wegaapi_json = error.f_str();
      syslog_ng("JSON error: " + err_wegaapi_json);
      cloudLog("ERROR", "JSON_parse_error:" + err_wegaapi_json);
    }
    else
    {
      // ========== КАЛИБРОВКА NTC (термистор) ==========
      if (doc.containsKey("tR_DAC") && doc.containsKey("tR_B"))
      {
        float tR_DAC = doc["tR_DAC"];
        float tR_B = doc["tR_B"];
        float tR_val_korr = doc["tR_val_korr"] | 0.0;
        
        if (NTC > 0 && tR_DAC > 0 && tR_B > 0)
        {
          float r = log((-NTC + tR_DAC) / NTC);
          wNTC = (tR_B * 25 - r * 237.15 * 25 - r * pow(237.15, 2)) / (tR_B + r * 25 + r * 237.15) + tR_val_korr;
        }
      }
      
      // ========== КАЛИБРОВКА EC (электропроводность) ==========
      if (doc.containsKey("EC_R1"))
      {
        String A1name = doc["A1"] | "Ap";
        String A2name = doc["A2"] | "An";
        float A1 = (A1name == "Ap") ? Ap : An;
        float A2 = (A2name == "An") ? An : Ap;
        float R1 = doc["EC_R1"];
        float Rx1 = doc["EC_Rx1"] | 0.0;
        float Rx2 = doc["EC_Rx2"] | 0.0;
        float Dr = doc["Dr"] | 4095.0;

        if (A1 > 0 && A2 > 0 && A1 < 4095 && A2 > 0)
        {
          float R2p = (((-A2 * R1 - A2 * Rx1 + R1 * Dr + Rx1 * Dr) / A2));
          float R2n = (-(-A1 * R1 - A1 * Rx2 + Rx2 * Dr) / (-A1 + Dr));
          wR2 = (R2p + R2n) / 2;

          // Расчет EC по калибровке
          if (wR2 > 0 && doc.containsKey("EC_val_p1"))
          {
            float ec1 = doc["EC_val_p1"];
            float ec2 = doc["EC_val_p2"];
            float ex1 = doc["EC_R2_p1"];
            float ex2 = doc["EC_R2_p2"];
            float eckorr = doc["EC_val_korr"] | 0.0;
            float kt = doc["EC_kT"] | 0.02;
            
            float eb = (-log10(ec1 / ec2)) / (log10(ex2 / ex1));
            float ea = pow(ex1, (-eb)) * ec1;
            float ec = ea * pow(wR2, eb);
            
            // Температурная компенсация
            float tempForEC = (wNTC > 0) ? wNTC : ((RootTemp > 0) ? RootTemp : 25);
            wEC = ec / (1 + kt * (tempForEC - 25)) + eckorr;
            
            cloudLog("DEBUG", "EC_calc:_wR2=" + String(wR2) + "_wEC=" + String(wEC));
          }
        }
      }

      // ========== КАЛИБРОВКА pH (3-точечная) ==========
      // Логируем состояние pH
      cloudLog("DEBUG", "pH_check:_pHmV=" + String(pHmV, 4) + "_hasKey=" + String(doc.containsKey("pH_raw_p1")));
      
      // ИСПРАВЛЕНО: убрана проверка pHmV как boolean, теперь проверяем только isnan
      if (!isnan(pHmV) && doc.containsKey("pH_raw_p1"))
      {
        float py1 = doc["pH_val_p1"];
        float py2 = doc["pH_val_p2"];
        float py3 = doc["pH_val_p3"];

        float px1 = doc["pH_raw_p1"];
        float px2 = doc["pH_raw_p2"];
        float px3 = doc["pH_raw_p3"];

        float pH_lkorr = doc["pH_lkorr"] | 0.0;
        
        cloudLog("DEBUG", "pH_calib:_p1=" + String(px1) + "_p2=" + String(px2) + "_p3=" + String(px3));

        // Проверка что калибровочные точки заданы
        if (px1 != 0 || px2 != 0 || px3 != 0)
        {
          // Квадратичная интерполяция по 3 точкам
          float pa = -(-px1*py3 + px1*py2 - px3*py2 + py3*px2 + py1*px3 - py1*px2) / 
                     (-pow(px1,2)*px3 + pow(px1,2)*px2 - px1*pow(px2,2) + px1*pow(px3,2) - pow(px3,2)*px2 + px3*pow(px2,2));
          float pb = (py3*pow(px2,2) - pow(px2,2)*py1 + pow(px3,2)*py1 + py2*pow(px1,2) - py3*pow(px1,2) - py2*pow(px3,2)) / 
                     ((-px3 + px2) * (px2*px3 - px2*px1 + pow(px1,2) - px3*px1));
          float pc = (py3*pow(px1,2)*px2 - py2*pow(px1,2)*px3 - pow(px2,2)*px1*py3 + pow(px3,2)*px1*py2 + pow(px2,2)*py1*px3 - pow(px3,2)*py1*px2) / 
                     ((-px3 + px2) * (px2*px3 - px2*px1 + pow(px1,2) - px3*px1));

          wpH = pa * pow(pHmV, 2) + pb * pHmV + pc + pH_lkorr;
          
          cloudLog("INFO", "pH_result:_pHmV=" + String(pHmV, 2) + "_wpH=" + String(wpH, 2));
        }
        else
        {
          cloudLog("WARN", "pH_calib_empty:_all_points_zero");
        }
      }
      else
      {
        cloudLog("WARN", "pH_skip:_pHmV_nan=" + String(isnan(pHmV)) + "_no_calib=" + String(!doc.containsKey("pH_raw_p1")));
      }
      
      // ========== УПРАВЛЕНИЕ ПОМПАМИ ==========
#if c_MCP23017 == 1
      if (doc.containsKey("pumps"))
      {
        JsonObject pumpsObj = doc["pumps"];
        
        if (pumpsObj.containsKey("pump1")) {
          bool state = pumpsObj["pump1"];
          mcp.digitalWrite(preferences.getInt("DRV1_A", 0), state ? HIGH : LOW);
          syslog_ng("Pump1: " + String(state));
        }
        if (pumpsObj.containsKey("pump2")) {
          bool state = pumpsObj["pump2"];
          mcp.digitalWrite(preferences.getInt("DRV1_B", 1), state ? HIGH : LOW);
          syslog_ng("Pump2: " + String(state));
        }
        if (pumpsObj.containsKey("pump3")) {
          bool state = pumpsObj["pump3"];
          mcp.digitalWrite(preferences.getInt("DRV1_C", 2), state ? HIGH : LOW);
          syslog_ng("Pump3: " + String(state));
        }
        if (pumpsObj.containsKey("pump4")) {
          bool state = pumpsObj["pump4"];
          mcp.digitalWrite(preferences.getInt("DRV1_D", 3), state ? HIGH : LOW);
          syslog_ng("Pump4: " + String(state));
        }
      }
#endif
    }

    http.end();

    // Переподключение WiFi если отвалился
    if (WiFi.status() != WL_CONNECTED)
    {
      syslog_ng("WiFi reconnecting...");
      cloudLog("WARN", "WiFi_reconnecting");
      WiFi.disconnect(true);
      WiFi.begin(ssid, password);
    }
    
    vTaskDelay(freqdb * 1000 / portTICK_PERIOD_MS);
  }
}

// ==================== ФУНКЦИЯ ОТПРАВКИ ЛОГОВ ====================
void cloudLog(String level, String message) {
    if (WiFi.status() != WL_CONNECTED) return;
    
    HTTPClient httpLog;
    WiFiClient clientLog;
    
    // Заменяем спецсимволы для URL
    message.replace(" ", "_");
    message.replace("=", ":");
    message.replace("&", "+");
    
    String url = wegaapi;
    url += "?action=log";
    url += "&device_id=" + String(HOSTNAME);
    url += "&api_key=" + wegaauth;
    url += "&level=" + level;
    url += "&msg=" + message;
    
    httpLog.begin(clientLog, url);
    httpLog.setTimeout(3000);
    httpLog.GET();
    httpLog.end();
}