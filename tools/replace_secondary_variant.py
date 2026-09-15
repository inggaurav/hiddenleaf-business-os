import os

dirs = ['resources/js', 'hrm-plugin/resources/js']
for d in dirs:
    if not os.path.exists(d):
        continue
    for root, _, files in os.walk(d):
        for f in files:
            if f.endswith(('.tsx', '.ts')):
                path = os.path.join(root, f)
                with open(path, 'r', encoding='utf-8') as fp:
                    content = fp.read()
                new_content = content.replace('variant="secondary"', 'variant="outline"')
                new_content = new_content.replace(": 'secondary'", ": 'outline'")
                new_content = new_content.replace("? 'secondary'", "? 'outline'")
                if new_content != content:
                    with open(path, 'w', encoding='utf-8') as fp:
                        fp.write(new_content)
                    print(f'Updated: {path}')
