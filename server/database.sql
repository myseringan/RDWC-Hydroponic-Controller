-- WegaBox Database Schema v2.1
-- Совместима с оригинальным протоколом WegaBox

-- Таблица показаний датчиков
CREATE TABLE IF NOT EXISTS readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL DEFAULT 'wegabox',
    uptime INT DEFAULT 0,
    
    -- Температура
    RootTemp FLOAT DEFAULT NULL COMMENT 'Температура корней (DS18B20)',
    AirTemp FLOAT DEFAULT NULL COMMENT 'Температура воздуха',
    AirHum FLOAT DEFAULT NULL COMMENT 'Влажность воздуха %',
    AirPress FLOAT DEFAULT NULL COMMENT 'Атмосферное давление hPa',
    CPUTemp FLOAT DEFAULT NULL COMMENT 'Температура CPU ESP32',
    
    -- pH
    pHmV FLOAT DEFAULT NULL COMMENT 'pH в милливольтах (RAW)',
    pHraw FLOAT DEFAULT NULL COMMENT 'pH RAW значение АЦП',
    wpH FLOAT DEFAULT NULL COMMENT 'pH вычисленный',
    
    -- EC (электропроводность)
    NTC FLOAT DEFAULT NULL COMMENT 'NTC термистор RAW',
    Ap FLOAT DEFAULT NULL COMMENT 'EC положительная фаза RAW',
    An FLOAT DEFAULT NULL COMMENT 'EC отрицательная фаза RAW',
    wNTC FLOAT DEFAULT NULL COMMENT 'Температура NTC вычисленная',
    wR2 FLOAT DEFAULT NULL COMMENT 'Сопротивление R2 вычисленное',
    wEC FLOAT DEFAULT NULL COMMENT 'EC вычисленный mS/cm',
    
    -- Уровень
    Dist FLOAT DEFAULT NULL COMMENT 'Расстояние до поверхности см',
    DstRAW FLOAT DEFAULT NULL COMMENT 'Расстояние RAW',
    
    -- Газы
    CO2 FLOAT DEFAULT NULL COMMENT 'CO2 ppm',
    tVOC FLOAT DEFAULT NULL COMMENT 'VOC ppb',
    
    -- Прочее
    PR FLOAT DEFAULT NULL COMMENT 'Фоторезистор (освещенность)',
    hall FLOAT DEFAULT NULL COMMENT 'Датчик Холла',
    RSSI INT DEFAULT NULL COMMENT 'WiFi сигнал dBm',
    readGPIO INT DEFAULT NULL COMMENT 'Состояние GPIO',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_device (device_id),
    INDEX idx_created (created_at),
    INDEX idx_device_created (device_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='История показаний датчиков';

-- Таблица калибровки
CREATE TABLE IF NOT EXISTS calibration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL UNIQUE DEFAULT 'wegabox',
    
    -- NTC термистор
    tR_DAC FLOAT DEFAULT 4095 COMMENT 'Максимальное значение АЦП',
    tR_B FLOAT DEFAULT 3950 COMMENT 'B-коэффициент NTC',
    tR_val_korr FLOAT DEFAULT 0 COMMENT 'Коррекция температуры NTC',
    
    -- EC калибровка
    EC_R1 FLOAT DEFAULT 1000 COMMENT 'Сопротивление R1 (Ом)',
    EC_Rx1 FLOAT DEFAULT 0 COMMENT 'Дополнительное сопротивление Rx1',
    EC_Rx2 FLOAT DEFAULT 0 COMMENT 'Дополнительное сопротивление Rx2',
    Dr FLOAT DEFAULT 4095 COMMENT 'Максимум АЦП',
    EC_val_p1 FLOAT DEFAULT 1.413 COMMENT 'EC точка 1 (mS/cm)',
    EC_val_p2 FLOAT DEFAULT 2.76 COMMENT 'EC точка 2 (mS/cm)',
    EC_R2_p1 FLOAT DEFAULT 1000 COMMENT 'R2 для точки 1 (Ом)',
    EC_R2_p2 FLOAT DEFAULT 500 COMMENT 'R2 для точки 2 (Ом)',
    EC_val_korr FLOAT DEFAULT 0 COMMENT 'Коррекция EC',
    EC_kT FLOAT DEFAULT 0.02 COMMENT 'Температурный коэффициент EC',
    
    -- pH калибровка (3 точки)
    pH_val_p1 FLOAT DEFAULT 4.0 COMMENT 'pH точка 1 (кислый буфер)',
    pH_val_p2 FLOAT DEFAULT 7.0 COMMENT 'pH точка 2 (нейтральный буфер)',
    pH_val_p3 FLOAT DEFAULT 10.0 COMMENT 'pH точка 3 (щелочной буфер)',
    pH_raw_p1 FLOAT DEFAULT 0 COMMENT 'RAW для pH 4.0',
    pH_raw_p2 FLOAT DEFAULT 0 COMMENT 'RAW для pH 7.0',
    pH_raw_p3 FLOAT DEFAULT 0 COMMENT 'RAW для pH 10.0',
    pH_lkorr FLOAT DEFAULT 0 COMMENT 'Линейная коррекция pH',
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Калибровочные коэффициенты';

-- Таблица помп
CREATE TABLE IF NOT EXISTS pumps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL UNIQUE DEFAULT 'wegabox',
    
    pump1 TINYINT(1) DEFAULT 0 COMMENT 'Помпа 1',
    pump2 TINYINT(1) DEFAULT 0 COMMENT 'Помпа 2',
    pump3 TINYINT(1) DEFAULT 0 COMMENT 'Помпа 3',
    pump4 TINYINT(1) DEFAULT 0 COMMENT 'Помпа 4',
    
    pump1_name VARCHAR(50) DEFAULT 'Насос 1',
    pump2_name VARCHAR(50) DEFAULT 'Насос 2',
    pump3_name VARCHAR(50) DEFAULT 'Насос 3',
    pump4_name VARCHAR(50) DEFAULT 'Насос 4',
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Управление помпами';

-- Начальные данные калибровки
INSERT INTO calibration (device_id) VALUES ('wegabox') 
ON DUPLICATE KEY UPDATE device_id = device_id;

-- Начальные данные помп
INSERT INTO pumps (device_id) VALUES ('wegabox')
ON DUPLICATE KEY UPDATE device_id = device_id;

