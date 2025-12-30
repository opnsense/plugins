#!/usr/local/bin/python3
"""
uploader.py - Data upload module for LightScope

Handles batching and uploading packet data and heartbeats to thelightscope.com
"""

import time
import threading
import sys
from collections import deque

try:
    import requests
    from requests.adapters import HTTPAdapter
except ImportError:
    print("Error: requests not found. Install with: pkg install py311-requests", file=sys.stderr)
    sys.exit(1)


# API Configuration
DATA_URL = "https://thelightscope.com/log_mysql_data"
HEARTBEAT_URL = "https://thelightscope.com/heartbeat"
HEADERS = {
    "Content-Type": "application/json",
    "X-API-Key": "lightscopeAPIkey2025_please_dont_distribute_me_but_im_write_only_anyways"
}

# Upload settings
BATCH_SIZE = 600
IDLE_FLUSH_SEC = 5.0
RETRY_BACKOFF = 5
MAX_QUEUE_SIZE = 100000


def send_data(consumer_pipe):
    """
    Main data upload function.

    Receives data from consumer_pipe, batches it, and uploads to thelightscope.com.
    Handles heartbeat messages separately.

    Args:
        consumer_pipe: multiprocessing.Pipe connection to receive data from
    """
    session = requests.Session()
    adapter = HTTPAdapter(pool_connections=4, pool_maxsize=4)
    session.mount("https://", adapter)
    session.mount("http://", adapter)

    queue = deque(maxlen=MAX_QUEUE_SIZE)
    last_activity = time.monotonic()
    stop_event = threading.Event()

    def reader():
        """Thread to read from pipe and add to queue."""
        nonlocal last_activity
        while not stop_event.is_set():
            try:
                item = consumer_pipe.recv()
                queue.append(item)
                last_activity = time.monotonic()
            except (EOFError, OSError):
                stop_event.set()
                break
            except Exception as e:
                print(f"uploader: Error receiving data: {e}", flush=True)

        # Drain any remaining data
        while consumer_pipe.poll(0):
            try:
                queue.append(consumer_pipe.recv())
            except:
                break

    # Start reader thread
    reader_thread = threading.Thread(target=reader, daemon=True)
    reader_thread.start()

    print("uploader: Started data upload service", flush=True)

    try:
        while not stop_event.is_set() or queue:
            # 1) Process heartbeats first
            hb_count = 0
            n = len(queue)
            for _ in range(n):
                try:
                    item = queue.popleft()
                except IndexError:
                    break

                if item.get("db_name") == "heartbeats":
                    hb_count += 1
                    try:
                        resp = session.post(
                            HEARTBEAT_URL,
                            json=item,
                            headers=HEADERS,
                            timeout=10
                        )
                        if resp.status_code != 200:
                            print(f"uploader: Heartbeat rejected ({resp.status_code})", flush=True)
                    except requests.RequestException as e:
                        print(f"uploader: Heartbeat error: {e}", flush=True)
                else:
                    # Put non-heartbeat items back
                    queue.append(item)

            if hb_count:
                print(f"uploader: Sent {hb_count} heartbeat(s)", flush=True)

            # 2) Batch and send regular data
            now = time.monotonic()
            elapsed = now - last_activity

            if queue and (len(queue) >= BATCH_SIZE or elapsed >= IDLE_FLUSH_SEC):
                to_send = min(len(queue), BATCH_SIZE)
                batch = [queue.popleft() for _ in range(to_send)]

                try:
                    resp = session.post(
                        DATA_URL,
                        json={"batch": batch},
                        headers=HEADERS,
                        timeout=10
                    )
                    if resp.status_code == 200:
                        print(f"uploader: Sent {to_send} items", flush=True)
                    else:
                        print(f"uploader: Upload rejected ({resp.status_code})", flush=True)
                    last_activity = time.monotonic()

                except requests.RequestException as e:
                    print(f"uploader: Upload error, will retry: {e}", flush=True)
                    # Put items back at front of queue
                    for item in reversed(batch):
                        queue.appendleft(item)
                    time.sleep(RETRY_BACKOFF)
            else:
                time.sleep(0.1)

    except KeyboardInterrupt:
        print("uploader: Shutting down...", flush=True)
    finally:
        stop_event.set()
        reader_thread.join(timeout=2)
        print("uploader: Stopped", flush=True)


def send_honeypot_data(consumer_pipe):
    """
    Honeypot-specific data upload function.

    Similar to send_data but with smaller batch sizes for honeypot data.

    Args:
        consumer_pipe: multiprocessing.Pipe connection to receive honeypot data from
    """
    session = requests.Session()
    adapter = HTTPAdapter(pool_connections=2, pool_maxsize=2)
    session.mount("https://", adapter)
    session.mount("http://", adapter)

    queue = deque(maxlen=MAX_QUEUE_SIZE)
    last_activity = time.monotonic()
    stop_event = threading.Event()

    HONEYPOT_BATCH_SIZE = 100

    def reader():
        nonlocal last_activity
        while not stop_event.is_set():
            try:
                item = consumer_pipe.recv()
                queue.append(item)
                last_activity = time.monotonic()
            except (EOFError, OSError):
                stop_event.set()
                break
            except Exception as e:
                print(f"honeypot_uploader: Error receiving data: {e}", flush=True)

        while consumer_pipe.poll(0):
            try:
                queue.append(consumer_pipe.recv())
            except:
                break

    reader_thread = threading.Thread(target=reader, daemon=True)
    reader_thread.start()

    print("honeypot_uploader: Started", flush=True)

    try:
        while not stop_event.is_set() or queue:
            now = time.monotonic()
            elapsed = now - last_activity

            if queue and (len(queue) >= HONEYPOT_BATCH_SIZE or elapsed >= IDLE_FLUSH_SEC):
                to_send = min(len(queue), HONEYPOT_BATCH_SIZE)
                batch = [queue.popleft() for _ in range(to_send)]

                try:
                    resp = session.post(
                        DATA_URL,
                        json={"batch": batch},
                        headers=HEADERS,
                        timeout=10
                    )
                    if resp.status_code == 200:
                        print(f"honeypot_uploader: Sent {to_send} items", flush=True)
                    else:
                        print(f"honeypot_uploader: Upload rejected ({resp.status_code})", flush=True)
                    last_activity = time.monotonic()

                except requests.RequestException as e:
                    print(f"honeypot_uploader: Error, will retry: {e}", flush=True)
                    for item in reversed(batch):
                        queue.appendleft(item)
                    time.sleep(RETRY_BACKOFF)
            else:
                time.sleep(0.1)

    except KeyboardInterrupt:
        pass
    finally:
        stop_event.set()
        reader_thread.join(timeout=2)
        print("honeypot_uploader: Stopped", flush=True)


if __name__ == "__main__":
    print("uploader: This module should be run as part of lightscope_daemon.py")
