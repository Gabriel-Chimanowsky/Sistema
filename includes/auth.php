<?php
session_start();

function checkAuth() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: login.php");
        exit;
    }
}

function isFinanceiro() {
    return isset($_SESSION['usuario_login']) && $_SESSION['usuario_login'] === 'Kamilla';
}

function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}
