-- phpMyAdmin SQL Dump
-- version 4.7.7
-- https://www.phpmyadmin.net/
--
-- Host: db_tolltech.mysql.dbaas.com.br
-- Generation Time: 30-Ago-2026 às 20:50
-- Versão do servidor: 5.7.32-35-log
-- PHP Version: 5.6.40-0+deb8u12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_tolltech`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `caminhoes`
--

CREATE TABLE `caminhoes` (
  `id` int(11) NOT NULL,
  `nome_caminhao` varchar(100) COLLATE latin1_general_ci DEFAULT NULL,
  `modelo` varchar(100) COLLATE latin1_general_ci DEFAULT NULL,
  `placa` varchar(10) COLLATE latin1_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `cargos`
--

CREATE TABLE `cargos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(50) COLLATE latin1_general_ci NOT NULL,
  `descricao` text COLLATE latin1_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Extraindo dados da tabela `cargos`
--

INSERT INTO `cargos` (`id`, `nome`, `descricao`) VALUES
(1, 'Administrador', 'Acesso total ao sistema'),
(2, 'usuario_comum', 'Acesso limitado às suas próprias informações');

-- --------------------------------------------------------

--
-- Estrutura da tabela `cod_servicos`
--

CREATE TABLE `cod_servicos` (
  `id` int(11) NOT NULL,
  `nome_servico` varchar(100) COLLATE latin1_general_ci NOT NULL,
  `descricao` text COLLATE latin1_general_ci,
  `preco` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `pecas`
--

CREATE TABLE `pecas` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) COLLATE latin1_general_ci NOT NULL,
  `categoria` varchar(100) COLLATE latin1_general_ci DEFAULT NULL,
  `marca` varchar(100) COLLATE latin1_general_ci DEFAULT NULL,
  `fornecedor` varchar(150) COLLATE latin1_general_ci DEFAULT NULL,
  `codigo` varchar(80) COLLATE latin1_general_ci DEFAULT NULL,
  `estoque` int(11) DEFAULT '0',
  `unidade` varchar(40) COLLATE latin1_general_ci DEFAULT 'Unidade',
  `valor` decimal(10,2) DEFAULT '0.00',
  `status` varchar(50) COLLATE latin1_general_ci DEFAULT 'Ativa',
  `imagem` text COLLATE latin1_general_ci,
  `descricao` text COLLATE latin1_general_ci,
  `data_cadastro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `empresas_cadastradas`
--

CREATE TABLE `empresas_cadastradas` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) COLLATE latin1_general_ci NOT NULL,
  `cnpj` varchar(20) COLLATE latin1_general_ci DEFAULT NULL,
  `site` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `contato` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `email` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `telefone` varchar(50) COLLATE latin1_general_ci DEFAULT NULL,
  `observacoes` text COLLATE latin1_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Extraindo dados da tabela `empresas_cadastradas`
--

INSERT INTO `empresas_cadastradas` (`id`, `nome`, `cnpj`, `site`, `contato`, `email`, `telefone`, `observacoes`) VALUES
(1, 'Danilo Dev', '111111111111111111', 'https://danilo-dev-sigma.vercel.app', 'Gestor', 'danilovicentindasilva@gmail.com', '19991293761', 'Teste de implementação de banco de dados');

-- --------------------------------------------------------

--
-- Estrutura da tabela `historico_atividades_empresas`
--

CREATE TABLE `historico_atividades_empresas` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `tipo` varchar(100) COLLATE latin1_general_ci NOT NULL,
  `mensagem` text COLLATE latin1_general_ci NOT NULL,
  `data_registro` datetime NOT NULL,
  `usuario` varchar(255) COLLATE latin1_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Extraindo dados da tabela `historico_atividades_empresas`
--

INSERT INTO `historico_atividades_empresas` (`id`, `empresa_id`, `tipo`, `mensagem`, `data_registro`, `usuario`) VALUES
(1, 1, 'registro', 'Empresa cadastrada no sistema Nexus HUB.', '2026-04-28 09:56:16', 'Julian Vane'),
(2, 1, 'manual', 'Teste Nota', '2026-04-28 10:22:49', 'Julian Vane');

-- --------------------------------------------------------

--
-- Estrutura da tabela `relatorio`
--

CREATE TABLE `relatorio` (
  `id` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `data_relatorio` date DEFAULT NULL,
  `servicos` int(11) DEFAULT NULL,
  `valor_total` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `servicos_prestados`
--

CREATE TABLE `servicos_prestados` (
  `id` int(11) NOT NULL,
  `id_relatorio` int(11) NOT NULL,
  `id_servico` int(11) NOT NULL,
  `id_caminhao` int(11) NOT NULL,
  `data_execucao` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(100) COLLATE latin1_general_ci NOT NULL,
  `email` varchar(150) COLLATE latin1_general_ci NOT NULL,
  `senha_hash` varchar(255) COLLATE latin1_general_ci NOT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ativo` tinyint(1) DEFAULT '1',
  `cargo_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Extraindo dados da tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha_hash`, `data_criacao`, `ativo`, `cargo_id`) VALUES
(1, 'Usuário Teste', 'teste@teste.com', '$2y$10$qdxhixw6uvGBQmyubgMLvOf4VWv7HtYH1c5H1xaEgo2z6iyxnPuCK', '2026-04-28 04:03:43', 1, 1),
(3, 'Danilo Vicentin da Silva', 'danilovicentindasilva@gmail.com', '$2y$10$HPgD6HmY8oTmintJgZpcauoDLKwNNvNF2w4ThgeVw9Gyx7pJUgrKC', '2026-04-28 17:55:34', 1, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `caminhoes`
--
ALTER TABLE `caminhoes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cargos`
--
ALTER TABLE `cargos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Indexes for table `cod_servicos`
--
ALTER TABLE `cod_servicos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `empresas_cadastradas`
--
ALTER TABLE `empresas_cadastradas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `historico_atividades_empresas`
--
ALTER TABLE `historico_atividades_empresas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Indexes for table `relatorio`
--
ALTER TABLE `relatorio`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_relatorio_empresa` (`id_empresa`);

--
-- Indexes for table `servicos_prestados`
--
ALTER TABLE `servicos_prestados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_prestado_relatorio` (`id_relatorio`),
  ADD KEY `fk_prestado_servico` (`id_servico`),
  ADD KEY `fk_prestado_caminhao` (`id_caminhao`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `caminhoes`
--
ALTER TABLE `caminhoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cargos`
--
ALTER TABLE `cargos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cod_servicos`
--
ALTER TABLE `cod_servicos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `empresas_cadastradas`
--
ALTER TABLE `empresas_cadastradas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `historico_atividades_empresas`
--
ALTER TABLE `historico_atividades_empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `relatorio`
--
ALTER TABLE `relatorio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `servicos_prestados`
--
ALTER TABLE `servicos_prestados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Limitadores para a tabela `historico_atividades_empresas`
--
ALTER TABLE `historico_atividades_empresas`
  ADD CONSTRAINT `historico_atividades_empresas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas_cadastradas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `relatorio`
--
ALTER TABLE `relatorio`
  ADD CONSTRAINT `fk_relatorio_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresas_cadastradas` (`id`);

--
-- Limitadores para a tabela `servicos_prestados`
--
ALTER TABLE `servicos_prestados`
  ADD CONSTRAINT `fk_prestado_caminhao` FOREIGN KEY (`id_caminhao`) REFERENCES `caminhoes` (`id`),
  ADD CONSTRAINT `fk_prestado_relatorio` FOREIGN KEY (`id_relatorio`) REFERENCES `relatorio` (`id`),
  ADD CONSTRAINT `fk_prestado_servico` FOREIGN KEY (`id_servico`) REFERENCES `cod_servicos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
