<?php
$servername = "localhost";
$username   = "root";
$password = ']\C2=ERR*_n0`j:?9F-"s9afN,eUl,QmEIPm6/AJL)~P:/A7]F';
$dbname     = "llm_ehr_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed");
}
?>
