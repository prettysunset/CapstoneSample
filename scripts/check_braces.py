import sys
p = r"c:\xampp\htdocs\capstone_sample\CapstoneSample\pc_per_office.php"
text = open(p, 'r', encoding='utf-8').read()
# limit to first PHP block only (up to the first '?>') so JavaScript braces don't affect counts
php_end = text.find('?>')
if php_end == -1:
    php_text = text
else:
    php_text = text[:php_end]

bal = 0
line = 1
results = []
for ch in php_text:
    if ch == '\n':
        line += 1
    if ch == '{':
        bal += 1
    elif ch == '}':
        bal -= 1
    results.append((line, bal, ch))

neg = [r for r in results if r[1] < 0]
if neg:
    print('Negative balance first at line', neg[0][0])
else:
    print('No negative balance encountered in PHP block')
print('Final PHP-block balance:', bal)

# find last few balance-change lines
changes = []
prev = 0
for idx,(ln,b,ch) in enumerate(results):
    if b != prev:
        changes.append((idx, ln, b))
    prev = b
if changes:
    print('Last balance changes (last 8):')
    for c in changes[-8:]:
        print(c)
    idx = changes[-1][0]
    start = max(0, idx-200)
    end = min(len(php_text), idx+200)
    print('\nContext around last change:')
    print(php_text[start:end])

# identify unmatched opening braces by scanning and using a stack
stack = []
line = 1
for i,ch in enumerate(php_text):
    if ch == '\n':
        line += 1
    if ch == '{':
        stack.append((line, i))
    elif ch == '}':
        if stack:
            stack.pop()
        else:
            print('Unmatched closing brace at line', line)

if stack:
    print('\nUnmatched opening brace(s) count:', len(stack))
    for ln, idx in stack[-8:]:
        snippet = php_text[max(0, idx-40): idx+40]
        print('Open brace at line', ln)
        print('Snippet:', snippet)
else:
    print('\nNo unmatched opening braces')
