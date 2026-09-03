# Facebook Account Manager V4.3

Sistema profissional para gerenciamento de contas do Facebook, automação de SMS (HeroSMS), integração com e-mails Hostinger, Cloudflare Email Routing e Slack Lists.

---

## 🚀 Estrutura do Projeto

O código é 100% modular, organizado e pronto para produção contínua:

```
sistema/
├── assets/                        # Arquivos estáticos front-end
│   └── js/                        # Scripts JS (common.js, tailwind.js, lucide.js)
│
├── backend/                       # Endpoints de API externa (REST com API_TOKEN)
│   ├── api_atualizar.php          # Atualização remota de status/vínculos
│   ├── api_gerar.php              # Geração remota de perfis e e-mails
│   └── api_listar.php             # Listagem remota de contas e clientes
│
├── docs/                          # Documentação técnica e de implantação
│   ├── DEPLOY.md                  # Guia completo de deploy em VPS / Contabo
│   └── RELATORIO_FUNCIONAMENTO_LOCAL.md # Relatório detalhado dos serviços
│
├── includes/                      # Núcleo modular do sistema
│   ├── auth.php                   # Sessão e controle de acesso
│   ├── db.php                     # Conexão PDO limpa e variáveis de ambiente
│   ├── migrations.php             # Gerenciador de migrações automáticas de schema
│   ├── components/                # Componentes compartilhados
│   │   └── navbar.php             # Barra de navegação responsiva e ações em lote
│   ├── helpers/                   # Módulos de serviço especializados
│   │   ├── cloudflare_helper.php  # Roteamento e regras de e-mail Cloudflare
│   │   ├── meta_helper.php        # Validação de apps via Meta Graph API
│   │   ├── slack_helper.php       # Automação de tarefas e listas no Slack
│   │   └── sms_helper.php         # Integração com API HeroSMS
│   └── lib/                       # Bibliotecas de terceiros
│       └── SimpleXLSXGen.php      # Geração de planilhas Excel (.xlsx)
│
├── sql/                           # Dumps e migrações do banco de dados
│   ├── database_schema.sql        # Schema completo para importação inicial
│   ├── descontos.sql              # Tabela de descontos financeiros
│   └── licencas.sql               # Base estruturada padrão
│
├── templates/                     # Modelos de arquivos do sistema
│   └── Import_template.xlsx       # Template oficial compatível com ixBrowser
│
├── tools/                         # Central de Ferramentas e Diagnóstico (Protegida)
│   ├── index.php                  # Painel central unificado
│   ├── debug_cf.php               # Diagnóstico de API Cloudflare
│   ├── debug_slack.php            # Teste de conexão do Slack
│   ├── debug_badge_slack.php      # Diagnóstico do badge de pendências
│   ├── forcar_lote_slack.php      # Forçar envio de lote parcial ao Slack
│   ├── reconstruir_slack.php      # Recriação do zero de listas do Slack
│   ├── limpar_bug_slack.php       # Correção de semanas duplicadas
│   ├── remover_teste.php          # Remoção controlada de contas de teste
│   ├── migrar_ids.php             # Reorganização de IDs por domínio
│   ├── verificar_config.php       # Verificação de credenciais ativas
│   └── verificar_log.php          # Logs e diagnósticos em tempo real
│
├── apps.php                       # Gestão e monitoramento de Apps Meta
├── cloudflare.php                 # Gestão de roteamento de e-mails Cloudflare
├── cloudflare_proxy.php           # Proxy intermediário local para API Cloudflare
├── conexao.php                    # Bootstrap do sistema (retrocompatível)
├── config.php                     # Painel de configurações gerais
├── cron_verificar_apps.php        # Cron de verificação de status de apps
├── index.php                      # Dashboard principal de contas/perfis
├── login.php / logout.php         # Autenticação de usuários
├── pessoas.php                    # Gerenciamento de clientes e pessoas
├── processa.php                   # Controlador central de ações POST
└── relatorio.php                  # Relatório financeiro e comissões
```

---

## 🛠️ Como Subir no Servidor (Docker)

1. **Configurar Ambiente:**
   ```bash
   cp .env.example .env
   # Edite as credenciais no .env
   ```

2. **Iniciar Contêineres:**
   ```bash
   docker-compose up -d --build
   ```

3. **Documentação Detalhada:**
   Consulte `docs/DEPLOY.md` para instruções de servidor de produção (Nginx, Certbot SSL, Crontab).
