/*
 ===============================================================================
 RADIOSCAN MATRIX v2.0 - XIAO SEED DETECTOR NODE FIRMWARE
 Platform: Seeed Studio XIAO (SAMD21 / RP2040 / ESP32-C3 / nRF52840)
 Purpose: 24-Channel Radiation Pulse Counting & RS-485 Modbus RTU / Serial Node
 ===============================================================================
 
 Modul Configuration:
   - Module 1 (XS1): SLAVE_ID = 1  (Channels D01 - D24)
   - Module 2 (XS2): SLAVE_ID = 2  (Channels D25 - D48)
   - Module 3 (XS3): SLAVE_ID = 3  (Channels D49 - D72)
 
 Hardware RS-485:
   - UART TX / RX -> MAX485 / SP3485 Transceiver (DI / RO)
   - DE / RE pin -> Flow control (HIGH = TX, LOW = RX)
 ===============================================================================
*/

#include <Arduino.h>

// -----------------------------------------------------------------------------
// 1. PENGATURAN MODUL & KOMUNIKASI
// -----------------------------------------------------------------------------
#define SLAVE_ID            1        // Ganti 1 untuk XS1, 2 untuk XS2, 3 untuk XS3
#define TOTAL_CHANNELS      24       // 24 Detektor per modul
#define RS485_BAUDRATE      115200   // Baudrate RS485
#define RS485_DE_PIN        2        // Pin Driver Enable MAX485 (HIGH=Kirim, LOW=Terima)

// -----------------------------------------------------------------------------
// 2. BUFFER PENYIMPANAN DATA CPS
// -----------------------------------------------------------------------------
volatile uint16_t pulse_counters[TOTAL_CHANNELS] = {0};
uint16_t cps_registers[TOTAL_CHANNELS] = {0};
unsigned long last_sampling_time = 0;

// -----------------------------------------------------------------------------
// 3. CRC-16 MODBUS CALCULATION
// -----------------------------------------------------------------------------
uint16_t calculate_modbus_crc(const uint8_t *buffer, uint8_t length) {
  uint16_t crc = 0xFFFF;
  for (uint8_t pos = 0; pos < length; pos++) {
    crc ^= (uint16_t)buffer[pos];
    for (uint8_t i = 8; i != 0; i--) {
      if ((crc & 0x0001) != 0) {
        crc >>= 1;
        crc ^= 0xA001;
      } else {
        crc >>= 1;
      }
    }
  }
  return crc;
}

// -----------------------------------------------------------------------------
// 4. MODBUS RTU SLAVE HANDLER
// -----------------------------------------------------------------------------
void handle_modbus_request() {
  if (Serial1.available() >= 8) {
    uint8_t request[8];
    for (int i = 0; i < 8; i++) {
      request[i] = Serial1.read();
    }

    // Cek apakah paket ditujukan untuk Slave ID ini
    if (request[0] != SLAVE_ID) {
      return;
    }

    // Cek Function Code (0x03 = Read Holding Registers)
    if (request[1] != 0x03) {
      return;
    }

    // Verifikasi CRC Request
    uint16_t req_crc = (request[7] << 8) | request[6];
    if (calculate_modbus_crc(request, 6) != req_crc) {
      return;
    }

    uint16_t num_registers = (request[4] << 8) | request[5];
    if (num_registers > TOTAL_CHANNELS) {
      num_registers = TOTAL_CHANNELS;
    }

    // Susun Response Modbus: [SlaveID, Func(0x03), ByteCount, Reg1_Hi, Reg1_Lo, ..., CRC_Lo, CRC_Hi]
    uint8_t byte_count = num_registers * 2;
    uint8_t response_len = 3 + byte_count + 2;
    uint8_t response[64];

    response[0] = SLAVE_ID;
    response[1] = 0x03;
    response[2] = byte_count;

    for (uint16_t i = 0; i < num_registers; i++) {
      response[3 + (i * 2)]     = (cps_registers[i] >> 8) & 0xFF;
      response[3 + (i * 2) + 1] = cps_registers[i] & 0xFF;
    }

    // Hitung CRC Response
    uint16_t resp_crc = calculate_modbus_crc(response, 3 + byte_count);
    response[3 + byte_count]     = resp_crc & 0xFF;
    response[3 + byte_count + 1] = (resp_crc >> 8) & 0xFF;

    // Aktifkan mode transmisi RS485 (DE/RE HIGH)
    digitalWrite(RS485_DE_PIN, HIGH);
    delayMicroseconds(50);
    
    Serial1.write(response, response_len);
    Serial1.flush();
    
    // Kembalikan ke mode penerimaan RS485 (DE/RE LOW)
    delayMicroseconds(50);
    digitalWrite(RS485_DE_PIN, LOW);
  }
}

// -----------------------------------------------------------------------------
// 5. UPDATE CPS (COUNTS PER SECOND)
// -----------------------------------------------------------------------------
void update_cps_readings() {
  unsigned long current_time = millis();
  if (current_time - last_sampling_time >= 1000) {
    last_sampling_time = current_time;

    // Kunci nilai CPS per detik dari akumulasi pulsa detektor
    noInterrupts();
    for (int i = 0; i < TOTAL_CHANNELS; i++) {
      // Baca pulsa nyata dari sensor (atau baseline jika diuji tanpa radiasi)
      cps_registers[i] = pulse_counters[i];
      pulse_counters[i] = 0; // Reset counter untuk detik berikutnya
    }
    interrupts();

    // Kirim juga stream ASCII/JSON ke USB Serial (untuk debugging langsung di PC)
    Serial.print("{\"module\":");
    Serial.print(SLAVE_ID);
    Serial.print(",\"cps\":[");
    for (int i = 0; i < TOTAL_CHANNELS; i++) {
      Serial.print(cps_registers[i]);
      if (i < TOTAL_CHANNELS - 1) Serial.print(",");
    }
    Serial.println("]}");
  }
}

// -----------------------------------------------------------------------------
// 6. SETUP & LOOP UTAMA
// -----------------------------------------------------------------------------
void setup() {
  // Serial USB (Debugging di PC)
  Serial.begin(115200);

  // Serial RS-485 (Serial1 Hardware UART di Xiao)
  Serial1.begin(RS485_BAUDRATE);

  pinMode(RS485_DE_PIN, OUTPUT);
  digitalWrite(RS485_DE_PIN, LOW); // Default mode RX (Receive)

  last_sampling_time = millis();
}

void loop() {
  // 1. Update perhitungan pulsa per detik
  update_cps_readings();

  // 2. Layani permintaan query Modbus RTU dari Python Master Gateway
  handle_modbus_request();
}
