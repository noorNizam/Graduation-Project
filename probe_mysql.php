<?php

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=graduation_project', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function q(PDO $pdo, string $sql): array
{
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

echo "== users ==\n";
var_export(q($pdo, 'SELECT id, email, created_at FROM users ORDER BY id'));
echo "\n== servings ==\n";
var_export(q($pdo, 'SELECT id, user_id, title, status, created_at FROM servings ORDER BY id'));
echo "\n== personal_access_tokens ==\n";
var_export(q($pdo, 'SELECT id, tokenable_id, name, created_at FROM personal_access_tokens ORDER BY id'));
echo "\n== migrations (last 5) ==\n";
var_export(q($pdo, 'SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 5'));
