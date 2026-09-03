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
Criaremos uma nova pasta chamada `/var/www/sistema` para manter o projeto isolado, sem interferir nos seus outros sistemas que já estão dentro de `/var/www/`.

```bash
cd /var/www
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

O sistema estará ativo localmente na porta `8081` da sua VPS Contabo.

---

## 7. Configuração de HTTPS / SSL Grátis (Sem derrubar seus outros sistemas!)

Como a sua VPS já possui outros sistemas rodando em `/var/www/`, você **provavelmente já tem um servidor Web (Apache ou Nginx) instalado** respondendo nas portas `80` e `443`.

> [!CAUTION]
> **NÃO instale outro servidor Web se já houver um rodando!** Se você instalar o Nginx por cima de um Apache existente, por exemplo, ele tentará usar a mesma porta e poderá derrubar todos os seus sites que estão no ar.

Abaixo, veja como configurar a integração dependendo do servidor que você já usa:

---

### OPÇÃO A: Se a sua VPS usa APACHE (Mais comum para sites em `/var/www/`)

Se o Apache já estiver instalado na VPS, basta criar um novo arquivo de "Virtual Host" para o seu domínio do sistema:

1. **Ativar os módulos de proxy do Apache (caso ainda não estejam ativos):**
   ```bash
   a2enmod proxy proxy_http
   systemctl restart apache2
   ```

2. **Criar a configuração do novo site:**
   ```bash
   nano /etc/apache2/sites-available/sistema.conf
   ```

3. **Coloque o seguinte conteúdo** (substitua `sistema.seudominio.com` pelo seu domínio real):
   ```apache
   <VirtualHost *:80>
       ServerName sistema.seudominio.com

       ProxyPreserveHost On
       ProxyPass / http://127.0.0.1:8081/
       ProxyPassReverse / http://127.0.0.1:8081/

       ErrorLog ${APACHE_LOG_DIR}/sistema-error.log
       CustomLog ${APACHE_LOG_DIR}/sistema-access.log combined
   </VirtualHost>
   ```

4. **Ativar o site e recarregar o Apache:**
   ```bash
   a2ensite sistema.conf
   systemctl reload apache2
   ```

5. **Gerar SSL com Certbot para Apache:**
   ```bash
   # Caso não tenha o certbot para Apache: apt install -y python3-certbot-apache
   certbot --apache -d sistema.seudominio.com
   ```

---

### OPÇÃO B: Se a sua VPS usa NGINX

Se a sua VPS já estiver rodando Nginx para os outros sistemas, **NÃO use `apt install nginx`**. Apenas adicione um novo bloco de servidor:

1. **Criar a configuração:**
   ```bash
   nano /etc/nginx/sites-available/sistema
   ```

2. **Coloque o seguinte conteúdo:**
   ```nginx
   server {
       listen 80;
       server_name sistema.seudominio.com;

       location / {
           proxy_pass http://127.0.0.1:8081;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

3. **Ativar o site e reiniciar o Nginx:**
   ```bash
   ln -s /etc/nginx/sites-available/sistema /etc/nginx/sites-enabled/
   nginx -t
   systemctl restart nginx
   ```

4. **Gerar SSL com Certbot para Nginx:**
   ```bash
   certbot --nginx -d sistema.seudominio.com
   ```

Pronto! Seu novo sistema rodará de forma totalmente isolada via Docker, usando o seu servidor web atual como proxy, sem interferir nem colocar em risco nenhum dos seus outros sistemas que já estão online! 🚀
