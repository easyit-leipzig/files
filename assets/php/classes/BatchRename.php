<?php
/**
 * Files – Reihenumbennung mit Muster, PHP >= 7.4.
 * Alle Pfade sind relativ zu einem serverseitig freigegebenen Panel-Wurzelordner.
 * Kein Zugriff auf absolute Client-Pfade und keine Umbenennung von Verzeichnissen.
 */
final class BatchRename
{
    private $roots;
    private $maxFiles;

    public function __construct(array $roots, int $maxFiles = 5000)
    {
        $this->roots = $roots;
        $this->maxFiles = $maxFiles;
    }

    private function root(string $panel): string
    {
        if (!isset($this->roots[$panel]) || !is_string($this->roots[$panel])) {
            throw new InvalidArgumentException('Unbekanntes Panel.');
        }
        $root = realpath($this->roots[$panel]);
        if (!$root || !is_dir($root)) {
            throw new RuntimeException('Der freigegebene Panel-Ordner existiert nicht.');
        }
        return $root;
    }

    private function relative(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        // Keine stillschweigende Änderung realer Dateinamen (z. B. Leerzeichen).
        if ($path === '' || $path[0] === '/' || strpos($path, "\0") !== false ||
            preg_match('~^[A-Za-z]:~', $path) || strpos($path, '//') !== false) {
            throw new InvalidArgumentException('Ungültiger relativer Dateipfad.');
        }
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..' || preg_match('/[\x00-\x1F]/', $part)) {
                throw new InvalidArgumentException('Ungültige Pfadkomponente.');
            }
        }
        return implode('/', $parts);
    }

    private function source(string $root, string $relative): string
    {
        $parts = explode('/', $relative);
        $p = rtrim($root, DIRECTORY_SEPARATOR);
        foreach ($parts as $part) {
            $p .= DIRECTORY_SEPARATOR . $part;
            if (is_link($p)) {
                throw new RuntimeException('Symbolische Links sind nicht zulässig.');
            }
        }
        $real = realpath($p);
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!$real || strpos($real, $prefix) !== 0 || !is_file($real)) {
            throw new RuntimeException('Datei fehlt oder liegt außerhalb des freigegebenen Ordners.');
        }
        return $real;
    }

    private function safeFilename(string $filename): void
    {
        if ($filename === '' || $filename === '.' || $filename === '..' ||
            strlen($filename) > 240 || preg_match('~[\x00-\x1F<>:"/\\\\|?*]~', $filename) ||
            preg_match('/[. ]$/', $filename) ||
            preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $filename)) {
            throw new InvalidArgumentException('Ungültiger Zieldateiname: ' . $filename);
        }
    }

    private function renameName(string $oldFilename, string $pattern, int $number): string
    {
        if ($pattern === '' || strlen($pattern) > 200 || strpos($pattern, "\0") !== false) {
            throw new InvalidArgumentException('Das Muster muss 1 bis 200 Zeichen enthalten.');
        }
        // Dateiendung immer erhalten (Dateien wie .gitignore behalten den gesamten Namen).
        $info = pathinfo($oldFilename);
        $extension = isset($info['extension']) && isset($info['filename']) && $info['filename'] !== ''
            ? '.' . $info['extension'] : '';
        $name = $extension !== '' ? $info['filename'] : $oldFilename;
        $unknown = preg_replace('/\{name\}|\{date\}|\{n(?::[1-9][0-9]?)?\}/', '', $pattern);
        if (strpos($unknown, '{') !== false || strpos($unknown, '}') !== false) {
            throw new InvalidArgumentException('Unbekannter Platzhalter im Muster.');
        }
        $stem = preg_replace_callback('/\{name\}|\{date\}|\{n(?::([1-9][0-9]?))?\}/',
            static function ($match) use ($name, $number) {
                if ($match[0] === '{name}') return $name;
                if ($match[0] === '{date}') return date('Y-m-d');
                $pad = isset($match[1]) && $match[1] !== '' ? min(20, (int)$match[1]) : 0;
                return $pad ? str_pad((string)$number, $pad, '0', STR_PAD_LEFT) : (string)$number;
            }, $pattern);
        if ($stem === null) throw new RuntimeException('Muster konnte nicht verarbeitet werden.');
        $new = $stem . $extension;
        $this->safeFilename($new);
        return $new;
    }

    private function entries(string $panel, array $paths, string $pattern, int $start, int $step): array
    {
        if (count($paths) < 1 || count($paths) > $this->maxFiles) {
            throw new InvalidArgumentException('1 bis ' . $this->maxFiles . ' Dateien auswählen.');
        }
        if ($start < 0 || $start > 1000000000 || $step < 1 || $step > 1000000) {
            throw new InvalidArgumentException('Startzahl oder Schrittweite ungültig.');
        }
        if ($start + (count($paths) - 1) * $step > 2000000000) {
            throw new InvalidArgumentException('Nummernbereich zu groß.');
        }
        $root = $this->root($panel);
        $rows = [];
        $sources = [];
        $targets = [];
        foreach ($paths as $i => $value) {
            if (!is_string($value)) throw new InvalidArgumentException('Die Dateiliste enthält ungültige Pfade.');
            $rel = $this->relative($value);
            $source = $this->source($root, $rel);
            if (isset($sources[$source])) throw new InvalidArgumentException('Eine Datei wurde mehrfach ausgewählt.');
            $sources[$source] = true;
            $filename = basename($source);
            $targetName = $this->renameName($filename, $pattern, $start + $i * $step);
            $dir = dirname($source);
            $target = $dir . DIRECTORY_SEPARATOR . $targetName;
            $key = $this->key($target);
            if (isset($targets[$key])) throw new InvalidArgumentException('Mehrere Dateien erhalten denselben Zielnamen.');
            $targets[$key] = true;
            $targetRelative = str_replace(DIRECTORY_SEPARATOR, '/', dirname($rel));
            $targetRelative = ($targetRelative === '.' ? '' : $targetRelative . '/') . $targetName;
            $rows[] = [
                'before' => $rel,
                'after' => $targetRelative,
                'changed' => $target !== $source,
                'source' => $source,
                'target' => $target
            ];
        }
        // Kollisionen mit nicht ausgewählten Dateien oder Verzeichnissen verhindern.
        foreach ($rows as $row) {
            if (!$row['changed']) continue;
            if ((file_exists($row['target']) || is_link($row['target'])) &&
                !isset($sources[(string)realpath($row['target'])])) {
                throw new RuntimeException('Ziel existiert bereits: ' . $row['after']);
            }
        }
        // Conservative Windows-kompatible Prüfung, auch wenn Linux case-sensitiv ist.
        $sourceKeys = [];
        foreach ($sources as $src => $_) $sourceKeys[$this->key($src)] = true;
        foreach ($rows as $row) {
            if (!$row['changed']) continue;
            $siblings = @scandir(dirname($row['source']));
            if ($siblings === false) throw new RuntimeException('Ordner kann nicht gelesen werden.');
            foreach ($siblings as $sibling) {
                if ($this->key(dirname($row['source']) . DIRECTORY_SEPARATOR . $sibling) === $this->key($row['target']) &&
                    !isset($sourceKeys[$this->key(dirname($row['source']) . DIRECTORY_SEPARATOR . $sibling)])) {
                    throw new RuntimeException('Zielname kollidiert mit vorhandener Datei: ' . $row['after']);
                }
            }
        }
        return $rows;
    }

    private function key(string $path): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($path, 'UTF-8') : strtolower($path);
    }

    public function preview(string $panel, array $paths, string $pattern, int $start = 1, int $step = 1): array
    {
        $rows = $this->entries($panel, $paths, $pattern, $start, $step);
        return [
            'ok' => true,
            'count' => count($rows),
            'changed' => count(array_filter($rows, static function ($r) { return $r['changed']; })),
            'items' => array_map(static function ($r) {
                return ['before' => $r['before'], 'after' => $r['after'], 'changed' => $r['changed']];
            }, $rows)
        ];
    }

    public function apply(string $panel, array $paths, string $pattern, int $start = 1, int $step = 1): array
    {
        $root = $this->root($panel);
        $lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'files-batch-rename-' . hash('sha256', $root) . '.lock';
        $lock = @fopen($lockPath, 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Sperre kann nicht gesetzt werden.');
        try {
            // Wichtig: innerhalb der Sperre aus aktuellen Dateinamen neu planen.
            $all = $this->entries($panel, $paths, $pattern, $start, $step);
            $rows = array_values(array_filter($all, static function ($r) { return $r['changed']; }));
            foreach ($rows as &$row) {
                // Temporärnamen im selben Ordner: auch A<->B-Tausch ist sicher.
                do {
                    $temp = dirname($row['source']) . DIRECTORY_SEPARATOR . '.files-rename-' . bin2hex(random_bytes(12)) . '.tmp';
                } while (file_exists($temp) || is_link($temp));
                $row['temp'] = $temp;
            }
            unset($row);
            $staged = [];
            try {
                foreach ($rows as $i => $row) {
                    if (!@rename($row['source'], $row['temp'])) {
                        throw new RuntimeException('Zwischenumbenennung fehlgeschlagen: ' . $row['before']);
                    }
                    $staged[] = $i;
                }
            } catch (Throwable $error) {
                foreach (array_reverse($staged) as $i) @rename($rows[$i]['temp'], $rows[$i]['source']);
                throw $error;
            }
            $committed = [];
            try {
                foreach ($rows as $i => $row) {
                    if (file_exists($row['target']) || is_link($row['target'])) {
                        throw new RuntimeException('Ziel wurde zwischenzeitlich belegt: ' . $row['after']);
                    }
                    if (!@rename($row['temp'], $row['target'])) {
                        throw new RuntimeException('Zielumbenennung fehlgeschlagen: ' . $row['after']);
                    }
                    $committed[] = $i;
                }
            } catch (Throwable $error) {
                $issues = [];
                foreach (array_reverse($committed) as $i) {
                    if (!@rename($rows[$i]['target'], $rows[$i]['temp'])) $issues[] = $rows[$i]['after'];
                }
                foreach (array_reverse($staged) as $i) {
                    if (file_exists($rows[$i]['temp']) && !@rename($rows[$i]['temp'], $rows[$i]['source'])) {
                        $issues[] = $rows[$i]['before'];
                    }
                }
                if ($issues) throw new RuntimeException($error->getMessage() . ' Rückabwicklung unvollständig: ' . implode(', ', $issues));
                throw $error;
            }
            return [
                'ok' => true,
                'changed' => count($rows),
                'count' => count($all),
                'items' => array_map(static function ($r) {
                    return ['before' => $r['before'], 'after' => $r['after'], 'changed' => $r['changed']];
                }, $all)
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function listing(string $panel, bool $recursive = true, int $limit = 5000): array
    {
        $root = $this->root($panel);
        $limit = min($this->maxFiles, max(1, $limit));
        $paths = [];
        $flags = FilesystemIterator::SKIP_DOTS;
        $iterator = $recursive
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, $flags))
            : new DirectoryIterator($root);
        foreach ($iterator as $file) {
            if ($file->isFile() && !$file->isLink()) {
                $path = $file->getPathname();
                $relative = substr($path, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1);
                $paths[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
                if (count($paths) >= $limit) break;
            }
        }
        natcasesort($paths);
        return ['ok' => true, 'items' => array_values($paths)];
    }
}
