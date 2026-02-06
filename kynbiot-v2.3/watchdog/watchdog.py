import os
import subprocess
import time
from datetime import datetime, timedelta

import psutil

NIM_CONTAINER = os.getenv('NIM_CONTAINER', 'nim')
CPU_LIMIT = float(os.getenv('WATCHDOG_CPU_LIMIT', '70'))
GPU_LIMIT = float(os.getenv('WATCHDOG_GPU_LIMIT', '50'))
POLL_SECONDS = int(os.getenv('WATCHDOG_POLL_SECONDS', '10'))
RESUME_IDLE_SECONDS = int(os.getenv('WATCHDOG_RESUME_IDLE_SECONDS', '300'))
BLOCKLIST = {'dazstudio.exe', 'photoshop.exe'}

paused_at = None


def get_gpu_utilization() -> float:
    try:
        out = subprocess.check_output(
            ['nvidia-smi', '--query-gpu=utilization.gpu', '--format=csv,noheader,nounits'],
            text=True,
        ).strip()
        return float(out.splitlines()[0])
    except Exception:
        return 0.0


def blocked_app_running() -> bool:
    for proc in psutil.process_iter(['name']):
        name = (proc.info.get('name') or '').lower()
        if name in BLOCKLIST:
            return True
    return False


def docker(action: str):
    subprocess.run(['docker', action, NIM_CONTAINER], check=False)


def is_paused() -> bool:
    out = subprocess.check_output(['docker', 'inspect', '-f', '{{.State.Status}}', NIM_CONTAINER], text=True).strip()
    return out == 'paused'


if __name__ == '__main__':
    while True:
        cpu = psutil.cpu_percent(interval=1)
        gpu = get_gpu_utilization()
        blocked = blocked_app_running()
        overloaded = cpu > CPU_LIMIT or gpu > GPU_LIMIT or blocked

        if overloaded and not is_paused():
            docker('pause')
            paused_at = datetime.utcnow()
            print(f'[watchdog] paused NIM cpu={cpu:.1f} gpu={gpu:.1f} blocked={blocked}')
        elif not overloaded and is_paused() and paused_at:
            idle_for = datetime.utcnow() - paused_at
            if idle_for >= timedelta(seconds=RESUME_IDLE_SECONDS):
                docker('unpause')
                print(f'[watchdog] resumed NIM after {idle_for}')
                paused_at = None

        time.sleep(POLL_SECONDS)
