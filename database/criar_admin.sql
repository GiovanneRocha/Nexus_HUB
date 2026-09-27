USE db_tolltech;

INSERT INTO cargos (id, nome, descricao)
VALUES (1, 'Administrador', 'Acesso total ao sistema')
ON DUPLICATE KEY UPDATE
  nome = VALUES(nome),
  descricao = VALUES(descricao);

INSERT INTO usuarios (nome, email, senha_hash, ativo, cargo_id)
VALUES (
  'Administrador Nexus HUB',
  'admin@nexushub.com.br',
  '$2y$10$07CHmSD5Ovo6912d81B7Cu1utEvjiM.Um9N2mcbt.BJ3po1LFK01q',
  1,
  1
)
ON DUPLICATE KEY UPDATE
  nome = VALUES(nome),
  senha_hash = VALUES(senha_hash),
  ativo = 1,
  cargo_id = 1;

SELECT id, nome, email, ativo, cargo_id
FROM usuarios
WHERE email = 'admin@nexushub.com.br';

-- Credenciais temporarias:
-- E-mail: admin@nexushub.com.br
-- Senha: Nexus@Admin2026!
-- Troque a senha depois do primeiro acesso, criando um novo hash PHP.
