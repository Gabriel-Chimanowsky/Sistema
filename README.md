# Facebook Account Manager V4.3

Sistema profissional para gerenciamento de contas do Facebook, automação de SMS (HeroSMS) e integração com e-mails Hostinger.

## 🚀 Como subir no Contabo (VPS)

### Requisitos
- Docker e Docker Compose instalados.

### Passo a Passo

1. **Clonar o Repositório:**
   ```bash
   git clone <url-do-seu-repositorio>
   cd sistema
   ```

2. **Configurar Ambiente:**
   - Copie o arquivo `.env.example` para `.env`:
     ```bash
     cp .env.example .env
     ```
   - Edite o `.env` com suas credenciais de API e e-mail.

3. **Rodar com Docker:**
   ```bash
   docker-compose up -d --build
   ```
   O sistema estará disponível na porta `8080`.

4. **Banco de Dados:**
   - O Docker já importa automaticamente o `database_schema.sql` na primeira inicialização.

## 📂 Estrutura do Projeto
- `index.php`: Painel principal de contas.
- `relatorio.php`: Painel financeiro.
- `pessoas.php`: Gerenciamento de clientes.
- `config.php`: Ajustes globais do sistema.
- `database_schema.sql`: Dump do banco de dados para importação.

## 🛠️ Tecnologias Utilizadas
- PHP 8.2
- MariaDB
- Tailwind CSS
- Lucide Icons
- SimpleXLSXGen (Exportação Excel)

---
Desenvolvido para gestão profissional de ativos.
