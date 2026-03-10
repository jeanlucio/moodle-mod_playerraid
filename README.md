# PlayerRaid - Plugin de Atividade Moodle

## Visão Geral

O **PlayerRaid** é um plugin de atividade para Moodle focado em gamificação cooperativa (PvE - Player vs Environment). Os alunos trabalham juntos para derrotar um "Chefão" (Boss) respondendo corretamente a perguntas sorteadas do Banco de Questões do Moodle.

## Características Principais

- **Gamificação Cooperativa**: Todos os alunos trabalham juntos contra um objetivo comum
- **Integração com Banco de Questões**: Utiliza categorias existentes do banco de questões do Moodle
- **Sistema de Penalidade Não Tóxico**: Respostas erradas aplicam cooldown individual ao invés de penalizar o grupo
- **Ferramenta de Revisão**: Excelente para revisão espaçada e engajamento contínuo

## Mecânica do Jogo

1. O professor define a vida total do chefão (Boss HP)
2. O professor seleciona uma categoria do banco de questões
3. Os alunos atacam o chefão respondendo perguntas aleatórias da categoria
4. Respostas corretas causam dano ao chefão (reduzem seu HP)
5. Respostas erradas aplicam um cooldown ao aluno que errou (sem penalizar o grupo)
6. O jogo termina quando o HP do chefão chega a 0

## Instalação

1. Extraia os arquivos para: `moodle/mod/playerraid/`
2. Acesse Administração do Site > Notificações
3. Siga as instruções de instalação

## Requisitos

- Moodle 4.0 ou superior
- Banco de questões com pelo menos uma categoria configurada

## Estrutura do Plugin

### Arquivos Principais
- [`version.php`](version.php) - Informações de versão e dependências
- [`lib.php`](lib.php) - Funções principais do plugin (CRUD)
- [`mod_form.php`](mod_form.php) - Formulário de configuração da atividade
- [`view.php`](view.php) - Interface principal para alunos
- [`attempt.php`](attempt.php) - Exibe questões aleatórias
- [`process.php`](process.php) - Processa respostas e atualiza o progresso

### Banco de Dados
- [`db/install.xml`](db/install.xml) - Esquema da tabela principal
- [`db/access.php`](db/access.php) - Definições de permissões

### Idiomas
- [`lang/en/playerraid.php`](lang/en/playerraid.php) - Strings em inglês
- [`lang/pt_br/playerraid.php`](lang/pt_br/playerraid.php) - Strings em português

### Backup e Restauração
- [`backup/moodle2/backup_playerraid_activity_task.class.php`](backup/moodle2/backup_playerraid_activity_task.class.php)
- [`backup/moodle2/backup_playerraid_stepslib.php`](backup/moodle2/backup_playerraid_stepslib.php)
- [`backup/moodle2/restore_playerraid_activity_task.class.php`](backup/moodle2/restore_playerraid_activity_task.class.php)
- [`backup/moodle2/restore_playerraid_stepslib.php`](backup/moodle2/restore_playerraid_stepslib.php)

## Capacidades (Permissões)

- **mod/playerraid:addinstance** - Adicionar nova instância da atividade
- **mod/playerraid:view** - Visualizar a atividade
- **mod/playerraid:attempt** - Tentar responder questões
- **mod/playerraid:manage** - Gerenciar configurações da atividade

## Integração com Question Bank

O plugin **não guarda cópias das questões**. Ele armazena apenas a referência (`questioncategoryid`) da categoria selecionada. Isso significa:

- ✅ Facilita manutenção (questões atualizadas refletem imediatamente)
- ✅ Não duplica conteúdo
- ⚠️ Mudanças na categoria afetam a atividade existente

## Conformidade

Este plugin foi desenvolvido seguindo:
- ✅ Moodle Coding Style Guidelines
- ✅ Bootstrap 5 (classes modernas: `ms-`, `me-`, `text-end`, etc.)
- ✅ Acessibilidade WCAG 2.1 (aria-labels, contraste, semântica)
- ✅ Padrões de segurança do Moodle (escapamento, prepared statements, sesskey)
- ✅ Autoloading e namespaces (Moodle 4.0+)

## Desenvolvimento Futuro

Melhorias planejadas:
- Tabela de tentativas individuais (tracking de cooldown por usuário)
- Sistema de dano variável baseado em dificuldade da questão
- Animações e feedback visual
- Ranking de contribuições
- Múltiplos chefões com dificuldades progressivas

## Autor

**Jean Lúcio**
Email: jeanlucio@gmail.com
Copyright: 2026

## Licença

GNU GPL v3 ou superior

---

**Parte do ecossistema de gamificação**: PlayerHUD | PlayerBoard | **PlayerRaid**
