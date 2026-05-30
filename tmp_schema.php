<?php
require 'db/db_con.php';
$c = db_connect();
foreach ($c->query('SHOW CREATE TABLE learning_modules') as $row) {
    echo $row['Create Table'];
}
