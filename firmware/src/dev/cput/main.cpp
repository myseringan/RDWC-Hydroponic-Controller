#if c_CPUTEMP == 1

#ifdef __cplusplus
extern "C" {
#endif
uint8_t temprature_sens_read();
#ifdef __cplusplus
}
#endif

unsigned long CPUTEMP_old = millis();
unsigned long CPUTEMP_Repeat = 120000;
RunningMedian CpuTempRM = RunningMedian(250);

float readTemp1(bool printRaw = false) {
  return (temprature_sens_read() - 32) / 1.8;
}

float readTemp2(bool printRaw = false) {
  return (temprature_sens_read() - 32) / 1.8;
}

#endif //c_CPUTEMP