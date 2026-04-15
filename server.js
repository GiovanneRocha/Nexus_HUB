const express = require("express")
const mysql = require("mysql2")
const jwt = require("jsonwebtoken")
const bcrypt = require("bcryptjs")
const cors = require("cors")
const path = require("path")

const app = express()
const PORT = 3000 // Porta do servidor (diferente do Apache do XAMPP, que é 80/443)
const SECRET_KEY = "vffY#ibW3Nao7T" // Mude para algo seguro, como uma string aleatória

// Middleware
app.use(cors()) // Permite requisições do frontend
app.use(express.json()) // Para parsear JSON
app.use(express.static(path.join(__dirname))) // Serve arquivos estáticos (HTML, CSS, JS)

// Conexão com MySQL usando Pool (mais robusto e com reconexão automática)
const db = mysql.createPool({
  host: "db_tolltech.mysql.dbaas.com.br",
  user: "db_tolltech", // Seu usuário MySQL
  password: "vffY#ibW3Nao7T", // Sua senha MySQL
  database: "db_tolltech", // Nome do banco
  waitForConnections: true,
  connectionLimit: 10, // Máximo de conexões simultâneas
  queueLimit: 0,
  enableKeepAlive: true, // Mantém a conexão viva
  keepAliveInitialDelayMs: 0,
})

db.on("error", (err) => {
  console.error("Erro na conexão do pool:", err.code)
  if (err.code === "PROTOCOL_CONNECTION_LOST") {
    console.log("Conexão perdida, tentando reconectar...")
  }
  if (err.code === "PROTOCOL_ENQUEUE_AFTER_FATAL_ERROR") {
    console.log("Erro fatal, reconectando...")
  }
  if (err.code === "PROTOCOL_ENQUEUE_AFTER_SOCKET_CLOSE") {
    console.log("Socket fechado, reconectando...")
  }
})

console.log("Pool de conexões MySQL iniciado!")

// Endpoint de login
app.post("/login", (req, res) => {
  const { email, password } = req.body
  const query = "SELECT * FROM usuarios WHERE email = ?"
  db.query(query, [email], (err, results) => {
    if (err) return res.status(500).json({ error: "Erro no banco" })
    if (results.length === 0)
      return res.status(401).json({ error: "Usuário não encontrado" })

    const user = results[0]
    bcrypt.compare(password, user.senha_ha, (err, isMatch) => {
      if (err) return res.status(500).json({ error: "Erro ao verificar senha" })
      if (!isMatch) return res.status(401).json({ error: "Senha incorreta" })

      // Gera token JWT
      const token = jwt.sign({ id: user.id, email: user.email }, SECRET_KEY, {
        expiresIn: "1h",
      })
      res.json({ token })
    })
  })
})

// Middleware para verificar token (opcional, para proteger rotas futuras)
const verifyToken = (req, res, next) => {
  const token = req.headers["authorization"]?.split(" ")[1] // Espera "Bearer <token>"
  if (!token) return res.status(403).json({ error: "Token necessário" })

  jwt.verify(token, SECRET_KEY, (err, decoded) => {
    if (err) return res.status(403).json({ error: "Token inválido" })
    req.user = decoded
    next()
  })
}

// Exemplo de rota protegida (pode adicionar mais)
app.get("/protected", verifyToken, (req, res) => {
  res.json({ message: "Acesso autorizado!", user: req.user })
})

// Inicia o servidor
app.listen(PORT, () => {
  console.log(`Servidor rodando em http://localhost:${PORT}`)
})
