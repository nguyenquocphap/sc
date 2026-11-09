# -*- coding: utf-8 -*-
import os, time, socket, subprocess

port = 14445
jar_cmd = 'java -Dfile.encoding=UTF-8 -Duser.timezone=Asia/Ho_Chi_Minh -Djava.awt.headless=true -server -XX:+UseSerialGC -Xms16m -Xmx2500m -Xss256k -XX:CICompilerCount=1 -XX:TieredStopAtLevel=1 -XX:MaxMetaspaceSize=48m -XX:CompressedClassSpaceSize=12m -XX:+SegmentedCodeCache -XX:+UseStringDeduplication -XX:MaxHeapFreeRatio=40 -XX:MinHeapFreeRatio=20 -cp "dist/*:lib/*" nro.models.server.ServerManager'
db_port = 3306

def is_port_open(p):
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(1.5)
    try:
        s.connect(("127.0.0.1", p))
        s.close()
        return True
    except:
        return False

print("=== WATCHDOG STARTED ===")

# Buoc 1: Kiem tra MySQL phai san sang
if not is_port_open(db_port):
    print("CSDL dang offline, doi CSDL online...")
    while not is_port_open(db_port):
        time.sleep(3)
    print("CSDL da online!")
    time.sleep(1)

# Buoc 2: Khoi dong ban dau neu chua chay
if not is_port_open(port):
    print("Khoi dong Server ban dau...")
    subprocess.Popen(jar_cmd, shell=True, start_new_session=True)
    time.sleep(15)

# Buoc 3: Vong lap giam sat
while True:
    if is_port_open(port):
        time.sleep(5)
        continue
    
    # Phat hien mat ket noi, doi 20 giay va kiem tra lai
    print("Phat hien mat ket noi! Doi 20 giay...")
    time.sleep(20)
    if is_port_open(port):
        print("Da phuc hoi ket noi!")
        continue
    
    print("Van mat ket noi sau 20 giay! Tien hanh khoi dong lai...")
    os.system(f"fuser -k -9 {port}/tcp >/dev/null 2>&1")
    os.system(f"lsof -t -i:{port} >/dev/null 2>&1 | xargs kill -9 >/dev/null 2>&1")
    os.system("pkill -9 -f 'ServerManager' >/dev/null 2>&1; pkill -9 -f 'VanTuan' >/dev/null 2>&1")
    time.sleep(2)
    
    subprocess.Popen(jar_cmd, shell=True, start_new_session=True)
    time.sleep(15)
