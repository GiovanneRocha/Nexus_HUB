CREATE DATABASE IF NOT EXISTS db_tolltech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_tolltech;

CREATE TABLE IF NOT EXISTS cargos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(50) NOT NULL,
  descricao TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY unique_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cargos (id, nome, descricao)
VALUES
  (1, 'Administrador', 'Acesso total ao sistema'),
  (2, 'usuario_comum', 'Acesso limitado às suas próprias informações')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao);

CREATE TABLE IF NOT EXISTS empresas_cadastradas (
  id INT NOT NULL AUTO_INCREMENT,
  nome VARCHAR(255) NOT NULL,
  cnpj VARCHAR(20) DEFAULT NULL,
  site VARCHAR(255) DEFAULT NULL,
  contato VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  telefone VARCHAR(50) DEFAULT NULL,
  observacoes TEXT DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO empresas_cadastradas (id, nome, cnpj, site, contato, email, telefone, observacoes)
VALUES
  (1, 'Danilo Dev', '111111111111111111', 'https://danilo-dev-sigma.vercel.app', 'Gestor', 'danilovicentindasilva@gmail.com', '19991293761', 'Teste de implementação de banco de dados')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), cnpj = VALUES(cnpj), site = VALUES(site), contato = VALUES(contato), email = VALUES(email), telefone = VALUES(telefone), observacoes = VALUES(observacoes);

CREATE TABLE IF NOT EXISTS caminhoes (
  id INT NOT NULL AUTO_INCREMENT,
  nome_caminhao VARCHAR(100) DEFAULT NULL,
  modelo VARCHAR(100) DEFAULT NULL,
  placa VARCHAR(10) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cod_servicos (
  id INT NOT NULL AUTO_INCREMENT,
  nome_servico VARCHAR(100) NOT NULL,
  descricao TEXT DEFAULT NULL,
  preco DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pecas (
  id INT NOT NULL AUTO_INCREMENT,
  nome VARCHAR(150) NOT NULL,
  categoria VARCHAR(100) DEFAULT NULL,
  marca VARCHAR(100) DEFAULT NULL,
  fornecedor VARCHAR(150) DEFAULT NULL,
  codigo VARCHAR(80) DEFAULT NULL,
  estoque INT DEFAULT 0,
  unidade VARCHAR(40) DEFAULT 'Unidade',
  valor DECIMAL(10,2) DEFAULT 0.00,
  status VARCHAR(50) DEFAULT 'Ativa',
  imagem TEXT DEFAULT NULL,
  descricao TEXT DEFAULT NULL,
  data_cadastro TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS historico_atividades_empresas (
  id INT NOT NULL AUTO_INCREMENT,
  empresa_id INT NOT NULL,
  tipo VARCHAR(100) NOT NULL,
  mensagem TEXT NOT NULL,
  data_registro DATETIME NOT NULL,
  usuario VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  KEY empresa_id (empresa_id),
  CONSTRAINT historico_atividades_empresas_ibfk_1 FOREIGN KEY (empresa_id) REFERENCES empresas_cadastradas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO historico_atividades_empresas (id, empresa_id, tipo, mensagem, data_registro, usuario)
VALUES
  (1, 1, 'registro', 'Empresa cadastrada no sistema Nexus HUB.', '2026-04-28 09:56:16', 'Julian Vane'),
  (2, 1, 'manual', 'Teste Nota', '2026-04-28 10:22:49', 'Julian Vane')
ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), mensagem = VALUES(mensagem), data_registro = VALUES(data_registro), usuario = VALUES(usuario);

CREATE TABLE IF NOT EXISTS relatorio (
  id INT NOT NULL AUTO_INCREMENT,
  id_empresa INT NOT NULL,
  data_relatorio DATE DEFAULT NULL,
  servicos INT DEFAULT NULL,
  valor_total DECIMAL(10,2) DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY fk_relatorio_empresa (id_empresa),
  CONSTRAINT fk_relatorio_empresa FOREIGN KEY (id_empresa) REFERENCES empresas_cadastradas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS servicos_prestados (
  id INT NOT NULL AUTO_INCREMENT,
  id_relatorio INT NOT NULL,
  id_servico INT NOT NULL,
  id_caminhao INT NOT NULL,
  data_execucao DATE DEFAULT NULL,
  PRIMARY KEY (id),
  KEY fk_prestado_relatorio (id_relatorio),
  KEY fk_prestado_servico (id_servico),
  KEY fk_prestado_caminhao (id_caminhao),
  CONSTRAINT fk_prestado_relatorio FOREIGN KEY (id_relatorio) REFERENCES relatorio (id),
  CONSTRAINT fk_prestado_servico FOREIGN KEY (id_servico) REFERENCES cod_servicos (id),
  CONSTRAINT fk_prestado_caminhao FOREIGN KEY (id_caminhao) REFERENCES caminhoes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  data_criacao TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  ativo TINYINT(1) DEFAULT 1,
  cargo_id INT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY unique_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO usuarios (id, nome, email, senha_hash, data_criacao, ativo, cargo_id)
VALUES
  (1, 'Usuário Teste', 'teste@teste.com', '$2y$10$qdxhixw6uvGBQmyubgMLvOf4VWv7HtYH1c5H1xaEgo2z6iyxnPuCK', '2026-04-28 04:03:43', 1, 1),
  (3, 'Danilo Vicentin da Silva', 'danilovicentindasilva@gmail.com', '$2y$10$HPgD6HmY8oTmintJgZpcauoDLKwNNvNF2w4ThgeVw9Gyx7pJUgrKC', '2026-04-28 17:55:34', 1, 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), email = VALUES(email), senha_hash = VALUES(senha_hash), data_criacao = VALUES(data_criacao), ativo = VALUES(ativo), cargo_id = VALUES(cargo_id);
