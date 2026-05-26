import sys
import os

# Add src to python path so we can import app
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app.bot.services.qris_service import extract_qris_payload_from_image

def main():
    if len(sys.argv) < 2:
        print("ERROR: Missing image path")
        sys.exit(1)
        
    img_path = sys.argv[1]
    if not os.path.exists(img_path):
        print(f"ERROR: File not found {img_path}")
        sys.exit(1)
        
    try:
        with open(img_path, 'rb') as f:
            image_bytes = f.read()
        
        payload = extract_qris_payload_from_image(image_bytes)
        print(f"PAYLOAD:{payload}")
    except Exception as e:
        print(f"ERROR:{str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
