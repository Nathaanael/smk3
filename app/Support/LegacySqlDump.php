<?php

namespace App\Support;

use RuntimeException;

/**
 * Reads the rows of one table from a phpMyAdmin / mysqldump file of the legacy system.
 */
class LegacySqlDump
{
    /**
     * Rows of every `INSERT INTO `table` (...) VALUES (...), (...);` statement, keyed by column name.
     *
     * @return list<array<string, string|int|float|null>>
     */
    public static function rows(string $path, string $table): array
    {
        $sql = @file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        $pattern = '/INSERT INTO `'.preg_quote($table, '/').'` \(([^)]*)\) VALUES\s*/i';
        if (! preg_match_all($pattern, $sql, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $rows = [];
        foreach ($matches[0] as $index => [$statement, $offset]) {
            $columns = array_map(fn (string $column) => trim($column, " `\r\n\t"), explode(',', $matches[1][$index][0]));

            foreach (self::tuples($sql, $offset + strlen($statement)) as $values) {
                if (count($values) !== count($columns)) {
                    throw new RuntimeException("Row with ".count($values)." values does not match the {$table} columns.");
                }
                $rows[] = array_combine($columns, $values);
            }
        }

        return $rows;
    }

    /**
     * Parse "(a, 'b', NULL), (...);" starting at $position.
     *
     * @return list<list<string|int|float|null>>
     */
    private static function tuples(string $sql, int $position): array
    {
        $tuples = [];
        $length = strlen($sql);
        $values = null;
        $token = '';
        $quoted = false;

        for ($i = $position; $i < $length; $i++) {
            $char = $sql[$i];

            if ($values === null) {
                if ($char === '(') {
                    $values = [];
                    $token = '';
                } elseif ($char === ';') {
                    break;
                }

                continue;
            }

            if ($char === "'") {
                $string = '';
                for ($i++; $i < $length; $i++) {
                    $char = $sql[$i];
                    if ($char === '\\') {
                        $next = $sql[++$i];
                        $string .= match ($next) {
                            'n' => "\n",
                            'r' => "\r",
                            't' => "\t",
                            '0' => "\0",
                            default => $next,
                        };
                    } elseif ($char === "'" && ($sql[$i + 1] ?? '') === "'") {
                        $string .= "'";
                        $i++;
                    } elseif ($char === "'") {
                        break;
                    } else {
                        $string .= $char;
                    }
                }
                $token = $string;
                $quoted = true;

                continue;
            }

            if ($char === ',' || $char === ')') {
                $values[] = self::value($token, $quoted);
                $token = '';
                $quoted = false;

                if ($char === ')') {
                    $tuples[] = $values;
                    $values = null;
                }

                continue;
            }

            if (! $quoted) {
                $token .= $char;
            }
        }

        return $tuples;
    }

    private static function value(string $token, bool $quoted): string|int|float|null
    {
        if ($quoted) {
            return $token;
        }

        $token = trim($token);

        return match (true) {
            strcasecmp($token, 'NULL') === 0 => null,
            preg_match('/^-?\d+$/', $token) === 1 => (int) $token,
            is_numeric($token) => (float) $token,
            default => $token,
        };
    }
}
