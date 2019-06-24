### APU LED Plugin ###

#### LED control for PC Engines APU platform OPNsense plugin ####

**Left -> Right**
LED1|LED2|LED3

At system boot, the 3 LEDs will blink (leds test).

|                                       | LED1     | LED2                      | LED3 |
|---------------------------------------|----------|---------------------------|------|
| System booting...                     | Blinking | OFF                       | OFF  |
| System OK                             | ON       | ON                        | ON   |
| CPU Load > 90%                        | Blinking | -                         | -    |
| Disk freespace < 10%                  | -        | Blinking (SOS morse code) | -    |
| WAN offline (without Internet access) | -        | Blinking fast             | -    |
| WAN packetloss > 30%                  | -        | Blinking slow             | -    |
| WAN online (Internet access)          | -        | ON                        | -    |
