import os
target = r'c:\Users\manag\Documents\Mr. Fox\hiddenleaf-business-os\hrm-plugin\src\Domain\HRM'
os.makedirs(target, exist_ok=True)
def save(name, content):
    with open(os.path.join(target, name), 'w', encoding='utf-8') as out:
        out.write(content.strip() + '\n')
    print('Saved', name)
