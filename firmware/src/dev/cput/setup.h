#if c_CPUTEMP == 1
  xTaskCreate(TaskCPUTEMP, "TaskCPUTEMP", 2000, NULL, 0, NULL);
#endif // c_CPUTEMP