<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($sql === false) throw new RuntimeException('Schema não encontrado.');
House\Database::connection()->exec($sql);
fwrite(STDOUT, "Banco atualizado com sucesso.\n");

