/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// WegaBox Custom - настройки для работы с вашим сервером                                               //
// Является частью проекта WEGA, https://github.com/wega_project                                        //
/////////////////////////////////////////////////////////////////////////////////////////////////////////////

// ==================== WiFi настройки ====================
const char* ssid = "SSID";           // Имя вашей WiFi сети
const char* password = "password";   // Пароль WiFi

// ==================== Сервер настройки ====================
// URL вашего сервера (например: http://155.212.166.41/api.php)
String wegaapi  = "your api";

// API ключ для авторизации
String wegaauth = "Your_wega_auth";

// Имя базы данных (для совместимости, можно оставить как есть)
String wegadb   = "wegabox";

// Частота отправки данных в секундах
long freqdb = 30;

// ==================== Имя устройства ====================
#define HOSTNAME "wegabox"   // Имя системы и mDNS: wegabox.local

// ==================== Подключенные датчики ====================
// 1 = датчик подключен, 0 = не подключен
// Установите 1 для датчиков которые у вас есть на плате

#define c_DS18B20   1   // Цифровой датчик температуры корней (1-Wire)
#define c_AHT10     1   // Датчик температуры и влажности воздуха (I2C)
#define c_AM2320    0   // Альтернативный датчик влажности (I2C)
#define c_CCS811    0   // Датчик CO2 и VOC (I2C)
#define c_hall      1   // Встроенный датчик Холла ESP32
#define c_CPUTEMP   1   // Температура процессора ESP32
#define c_MCP3421   1   // 18-bit АЦП для pH (I2C)
#define c_ADS1115   0   // 16-bit АЦП для pH (I2C) - основной
#define c_NTC       1   // Термистор NTC 100K для EC
#define c_EC        1   // Датчик электропроводности EC
#define c_US025     1   // Ультразвуковой датчик уровня
#define c_MCP23017  1   // GPIO расширитель для помп (I2C)
#define c_PR        1   // Фоторезистор GL5528
#define c_BMP280    0   // Датчик давления (I2C)
#define c_HX710B    0   // Датчик давления для уровня
#define c_DualBMx   0   // Сдвоенный BMP280
#define c_SDC30     0   // NDIR CO2 датчик (I2C)
#define c_LCD       0   // OLED дисплей SSD1306
#define c_VL6180X   0   // Лазерный дальномер (I2C гнездо)
#define c_VL6180X_us 0  // Лазерный дальномер (US гнездо)
#define c_VL53L0X_us 0  // Лазерный дальномер VL53L0X

// ==================== Syslog (опционально) ====================
#define c_syslog    0   // 1 = включить отправку логов на syslog сервер
#define SYSLOG_SERVER "10.20.30.40"
#define SYSLOG_PORT 514

// ==================== Имена полей в базе данных ====================
// Эти имена используются при отправке данных на сервер
String db_AirTemp="AirTemp";
String db_AirHum="AirHum";
String db_AirPress="AirPress";
String db_RootTemp="RootTemp";
String db_pHmV="pHmV";
String db_pHraw="pHraw";
String db_NTC="NTC";
String db_Ap="Ap";
String db_An="An";
String db_Dist="Dist";
String db_PR="PR";
String db_CO2="CO2";
String db_tVOC="tVOC";
String db_hall="hall";
String db_CPUTemp="CPUTemp";
