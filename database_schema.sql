-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 14/05/2026 às 17:16
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `licencas`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes`
--

CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `senha_padrao` varchar(255) NOT NULL,
  `email_contador` int(11) NOT NULL DEFAULT 1,
  `genero_padrao` varchar(20) NOT NULL DEFAULT 'homem',
  `pais_padrao` varchar(10) NOT NULL DEFAULT 'br',
  `email_prefixo` varchar(50) DEFAULT 'contato',
  `email_dominio` varchar(50) DEFAULT '@dollfinn.com',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `configuracoes`
--

INSERT INTO `configuracoes` (`id`, `senha_padrao`, `email_contador`, `genero_padrao`, `pais_padrao`, `email_prefixo`, `email_dominio`) VALUES
(1, 'lfLCkTaUJB', 1, 'mulher', 'us', 'contato', '@punpikin.com');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pessoas`
--

CREATE TABLE IF NOT EXISTS `pessoas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `pessoas`
--

INSERT INTO `pessoas` (`id`, `nome`) VALUES
(1, 'Jennifer'),
(4, 'Carol'),
(5, 'Elaine'),
(6, 'Mabel'),
(7, 'Lori');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `login`, `senha`, `nome`) VALUES
(1, 'admin', '$2y$10$RdibZuohqOg6qFjCsSwN6uE0GYFe/Qtb4HoOrQEKeuc8v0WpAftne', 'Administrador'),
(2, 'Kamilla', '$2y$10$RdibZuohqOg6qFjCsSwN6uE0GYFe/Qtb4HoOrQEKeuc8v0WpAftne', 'Kamilla');

-- --------------------------------------------------------

--
-- Estrutura para tabela `contas`
--

CREATE TABLE IF NOT EXISTS `contas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `sobrenome` varchar(100) NOT NULL,
  `username` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `genero` enum('homem','mulher') NOT NULL,
  `pais` varchar(50) NOT NULL,
  `status` enum('pendente','criada','autenticada','exportado') DEFAULT 'pendente',
  `data_criacao` datetime DEFAULT NULL,
  `destinada_a` int(11) DEFAULT NULL,
  `chave_2fa` varchar(255) DEFAULT NULL,
  `codigo_2fa` varchar(50) DEFAULT NULL,
  `data_autenticacao` datetime DEFAULT NULL,
  `cookies` longtext DEFAULT NULL,
  `data_vinculo` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `destinada_a` (`destinada_a`),
  CONSTRAINT `contas_ibfk_1` FOREIGN KEY (`destinada_a`) REFERENCES `pessoas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `contas`
--

INSERT INTO `contas` (`id`, `nome`, `sobrenome`, `username`, `email`, `senha`, `genero`, `pais`, `status`, `data_criacao`, `destinada_a`, `chave_2fa`, `codigo_2fa`, `data_autenticacao`, `cookies`, `data_vinculo`) VALUES
(4, 'Elaine', 'Debusk', 'elaine-debusk', 'contato4@dollfinn.com', '2lYU52%fd-}J', 'mulher', 'us', 'exportado', NULL, 5, '592345', 'IICJ IVGT BD3W KACC GCXC MPIF COQM KSY3', '2026-03-29 10:05:27', '[ {     \"domain\": \".facebook.com\",     \"expirationDate\": 1808420061.29135,     \"hostOnly\": false,     \"httpOnly\": false,     \"name\": \"c_user\",     \"path\": \"/\",     \"sameSite\": \"no_restriction\",     \"secure\": true,     \"session\": false,     \"storeId\": \"0\",     \"value\": \"61573674806744\",     \"id\": 1 }, {     \"domain\": \".facebook.com\",     \"expirationDate\": 1811261161.653599,     \"hostOnly\": false,     \"httpOnly\": true,     \"name\": \"datr\",     \"path\": \"/\",     \"sameSite\": \"no_restriction\",     \"secure\": true,     \"session\": false,     \"storeId\": \"0\",     \"value\": \"6U7maScdK7A66iBcnYH5I6wZ\",     \"id\": 2 }, {     \"domain\": \".facebook.com\",     \"expirationDate\": 1777488861,     \"hostOnly\": false,     \"httpOnly\": false,     \"name\": \"dpr\",     \"path\": \"/\",     \"sameSite\": \"no_restriction\",     \"secure\": true,     \"session\": false,     \"storeId\": \"0\",     \"value\": \"1.25\",     \"id\": 3 }, {     \"domain\": \".facebook.com\",     \"expirationDate\": 1784660061.291479,     \"hostOnly\": false,     \"httpOnly\": true,     \"name\": \"fr\",     \"path\": \"/\",     \"sameSite\": \"no_restriction\",     \"secure\": true,     \"session\": false,     \"storeId\": \"0\",     \"value\": \"1scAwVhOZL1MAKDvB.AWfz9JgiV73W-UQJC2sh0lK1dhv7bYdBT3tMjc56wBXRu2lzmr4.Bp6Rld..AAA.0.0.Bp6Rld.AWfxIctbyBDeBwODHKxSzuq_BVI\",     \"id\": 4 }, {     \"domain\": \".facebook.com\",     \"hostOnly\": false,     \"httpOnly\": false,     \"name\": \"presence\",     \"path\": \"/\",     \"sameSite\": \"unspecified\",     \"secure\": true,     \"session\": true,     \"storeId\": \"0\",     \"value\": \"C%7B%22t3%22%3A%5B%5D%2C%22utc3%22%3A1776884062691%2C%22v%22%3A1%7D\",     \"id\": 5 }, {     \"domain\": \".facebook.com\",     \"expirationDate\": 1811444060.008441,     \"hostOnly\": false,     \"httpOnly\": true,     \"name\": \"ps_l\",     \"path\": \"/\",     \"sameSite\": \"lax\",     \"secure\": true,     \"session\": false,     \"storeId\": \"0\",     \"value\": \"1\",     \"id\": 6 }, {     \"domain\": \".facebook.com\",     \"expirationDate\": 1811444060.008572,     \"hostOnly\": false,     \"httpOnly\": true,     \"name\": \"ps_n\",     \"path\": \"/\",     \"sameSite\": \"no_restriction\",     \"secure\": true,     \"session\": false,     \"storeId\": \"0\",     \"value\": \"1\",     \"id\": 7 }, {     \"domain\": \".facebook.com\",     \"expirationDate\": 1811444059.444007,     \"hostOnly\": false,     \"httpOnly\": true,     \"name\": \"sb\",     \"path\": \"/\",     \"sameSite\": \"no_restriction\",     \"secure\": true,     \"session\": false,     \"storeId\": \"0\",     \"value\": \"6U7maawrapj5cN6do-iwpjEr\",     \"id\": 8 }, {     \"domain\": \".facebook.com\",     \"expirationDate\": 1777488861,     \"hostOnly\": false,     \"httpOnly\": false,     \"name\": \"wd\",     \"path\": \"/\",     \"sameSite\": \"lax\",     \"secure\": true,     \"session\": false,     \"storeId\": \"0\",     \"value\": \"1536x730\",     \"id\": 9 }, {     \"domain\": \".facebook.com\",     \"expirationDate\": 1808420061.291568,     \"hostOnly\": false,     \"httpOnly\": true,     \"name\": \"xs\",     \"path\": \"/\",     \"sameSite\": \"no_restriction\",     \"secure\": true,     \"session\": false,     \"storeId\": \"0\",     \"value\": \"7%3AMVoGdTxFnizfxg%3A2%3A1776884056%3A-1%3A-1%3A%3AAcw0GJepeXEPa85cRUHVdN3Y6p4j7lIw-cNXLHliaQ\",     \"id\": 10 } ]', '2026-04-11 10:00:00');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
