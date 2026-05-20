# 🚀 Guia de Deploy - Contabo VPS via GitHub

Este guia descreve o processo passo a passo para implantar este sistema na sua VPS Contabo utilizando Docker e Docker Compose, sincronizado diretamente com o seu repositório no GitHub.

---

## 📋 Sumário
1. [Preparação Local](#1-preparação-local)
2. [Acesso à VPS Contabo](#2-acesso-à-vps-contabo)
3. [Instalação do Docker na VPS](#3-instalação-do-docker-na-vps)
4. [Clone e Configuração do Projeto](#4-clone-e-configuração-do-projeto)
5. [Execução e Inicialização](#5-execução-e-inicialização)
6. [Configuração de HTTPS / SSL Grátis (Opcional & Recomendado)](#6-configuração-de-https--ssl-grátis-opcional--recomendado)

---

## 1. Preparação Local

Antes de ir para a VPS, garanta que todas as alterações locais (incluindo o ajuste do banco de dados no `docker-compose.yml`) foram enviadas para o seu repositório GitHub.

No terminal do seu computador (dentro da pasta do projeto), execute:

```bash
# Adiciona as alterações
git add .

# Registra o commit
git commit -m "chore: prepara configuracoes para deploy na Contabo"

# Envia para o GitHub
git push origin main
```

---

## 2. Acesso à VPS Contabo

Abra o seu terminal (ou PowerShell no Windows) e conecte-se à sua VPS utilizando o IP fornecido pela Contabo:

```bash
ssh root@IP_DA_SUA_VPS
```
*(Substitua `IP_DA_SUA_VPS` pelo endereço IP real da sua máquina Contabo e digite a senha quando solicitado).*

---

## 3. Instalação do Docker na VPS

Uma vez dentro da VPS (geralmente rodando Ubuntu ou Debian), execute os comandos abaixo para atualizar o sistema e instalar o **Docker** e o **Docker Compose** usando os repositórios oficiais:

```bash
# 1. Atualizar a lista de pacotes
apt update && apt upgrade -y

# 2. Instalar dependências iniciais
apt install -y ca-certificates curl gnupg lsb-release

# 3. Adicionar a chave GPG oficial do Docker
mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# 4. Configurar o repositório estável do Docker
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

# 5. Instalar o Docker Engine e Docker Compose
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# 6. Habilitar o Docker para iniciar junto com o sistema
systemctl enable docker
systemctl start docker
```

---

## 4. Clone e Configuração do Projeto

Como o seu repositório é **público**, você não precisa configurar chaves SSH ou tokens na VPS! A clonagem é feita de forma extremamente simples e direta por HTTPS.

### A. Clonar o repositório:
```bash
cd /var
git clone https://github.com/Gabriel-Chimanowsky/Sistema.git sistema
cd sistema
```

### B. Configurar as variáveis de ambiente:
Crie o arquivo `.env` de produção a partir do modelo:
```bash
cp .env.example .env
```

Agora, edite as variáveis para colocar as credenciais de produção (use o editor nano):
```bash
nano .env
```
*(Ajuste o `DB_PASS` para uma senha forte e coloque as credenciais corretas para o e-mail e APIs. Pressione `Ctrl + O` e depois `Enter` para salvar, e `Ctrl + X` para sair).*

---

## 6. Execução e Inicialização

O sistema agora está pronto para rodar!

### A. Iniciar os containers em segundo plano:
```bash
docker compose up -d --build
```

O Docker irá baixar as imagens do PHP e MariaDB, construir a sua imagem personalizada baseada no `Dockerfile`, importar automaticamente a estrutura inicial contida em `./sql/licencas.sql` e disponibilizar a aplicação.

### B. Verificar se os containers estão rodando:
```bash
docker compose ps
```

O sistema estará ativo localmente na porta `8080` da sua VPS Contabo.

---

## 7. Configuração de HTTPS / SSL Grátis (Opcional & Recomendado)

Para que seu sistema seja acessado de forma segura através do seu domínio (ex: `sistema.seudominio.com`) utilizando HTTPS, configure o **Nginx** como proxy reverso e use o **Certbot** para gerar o certificado SSL grátis da Let's Encrypt.

### A. Instalar o Nginx e Certbot na VPS:
```bash
apt install -y nginx certbot python3-certbot-nginx
```

### B. Configurar o Nginx:
Crie um arquivo de configuração para o seu domínio:
```bash
nano /etc/nginx/sites-available/sistema
```

Cole o seguinte conteúdo (substitua `sistema.seudominio.com` pelo seu domínio real apontado para a VPS):

```nginx
server {
    listen 80;
    server_name sistema.seudominio.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Ative o site e reinicie o Nginx:
```bash
ln -s /etc/nginx/sites-available/sistema /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
```

### C. Gerar o Certificado SSL HTTPS Grátis:
Execute o comando abaixo e siga as instruções na tela. O Certbot irá configurar o SSL automaticamente no seu arquivo do Nginx!
```bash
certbot --nginx -d sistema.seudominio.com
```

Pronto! Seu sistema agora está rodando de forma profissional, segura (HTTPS) e de fácil manutenção direto na sua VPS Contabo! 🚀
