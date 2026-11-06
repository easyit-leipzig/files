"""Static regression tests for the help integration (stdlib only)."""
from pathlib import Path
root = Path(__file__).resolve().parents[1]
html = (root / 'index.php').read_text(encoding='utf8')
js = (root / 'assets/javascript/classes/FilesHelp.js').read_text(encoding='utf8')
css = (root / 'assets/css/files.css').read_text(encoding='utf8')
assert 'src="assets/javascript/classes/FilesHelp.js' in html  # Optional cache-busting query
assert html.index('FilesHelp.js') < html.index('nj.ready(')
for topic in ['overview','globalFilter','duplicates','drives','folders','filter',
              'invert','recursive','columns','sorting','selection','removeName',
              'batchRename','actions','transfer','folderDialog','columnDialog']:
    assert topic + ': [' in js, topic
for side in ('left', 'right'):
    for prefix in ('drive','folder','apply-filter','columns','batch-rename','remove-name'):
        assert "'#" + prefix + '-' + side + "'" in js, (prefix, side)
assert '.files-help-trigger' in css
assert 'help.showModal()' in js
assert 'addEventListener(\'close\'' in js
print('PASS: Help topics, both panels, dialogs, JavaScript integration and CSS')
