# MK3 Multimarcas

Site e painel em PHP puro, PDO/MySQL, HTML, CSS e JavaScript. Sem Laravel, WordPress ou Composer. Preto, vermelho e branco, com a logo original preservada. Nenhum veículo ou administrador é criado automaticamente.

## Executar no XAMPP / Windows

Requisitos: PHP **8.2 ou superior**, MySQL 8 ou MariaDB 10.4+, Apache 2.4 e extensões `pdo_mysql`, `mbstring`, `fileinfo`, `gd` com WebP. Habilite `exif` para orientar corretamente fotografias JPEG de celular. Para os testes HTTP em PHP, habilite também `curl`.

1. Copie o conteúdo desta pasta `MK3` para `C:\xampp\htdocs\mk3`.
2. Inicie Apache e MySQL no XAMPP. Importe `database.sql` pelo phpMyAdmin. O arquivo é idempotente: não apaga tabelas existentes.
3. Copie `app/config.local.example.php` para `app/config.local.php` e ajuste os dados do banco se necessário. O arquivo local é ignorado pelo Git. Também são aceitas `MK3_DB_HOST`, `MK3_DB_PORT`, `MK3_DB_NAME`, `MK3_DB_USER`, `MK3_DB_PASS` e `MK3_LOCAL_HTTP` no processo PHP. Nunca versione senhas.
4. Execute no terminal:

   ```bat
   cd C:\xampp\htdocs\mk3
   C:\xampp\php\php.exe app\migrate.php
   ```

   Para uma instalação anterior, **faça backup do banco e de uploads antes da migração**. A migração adiciona campos e converte anos legados no formato `2020/2021`, sem descartar registros; pode ser executada novamente.

5. Ajuste `C:\xampp\php\php.ini` para fotos em lote e reinicie o Apache:

   ```ini
   upload_max_filesize=5M
   post_max_size=64M
   max_file_uploads=12
   memory_limit=256M
   ```

6. Abra o instalador **localmente**, em `http://localhost/mk3/instalar.php`, e crie a conta administrativa com senha própria de 12 a 72 bytes. A primeira conta bloqueia novas instalações automaticamente, inclusive se o navegador repetir a solicitação. Remova `instalar.php` da hospedagem após instalar. Instalação remota exige `MK3_INSTALL_TOKEN` no servidor e no formulário; não forneça esse token na URL.
7. Site: `http://localhost/mk3/`. Painel: `http://localhost/mk3/admin/login.php`.
8. Mantenha `AllowOverride All` habilitado para a pasta no Apache, pois os arquivos `.htaccess` fazem parte da proteção. A pasta `uploads` precisa ser gravável pelo PHP; o restante do código não precisa de escrita.

A logo original permanece em `assets/logo-mk3-oficial.png`. Os dados institucionais iniciais vieram do projeto existente; confirme-os com a empresa antes da entrega. Configurações só são alteradas no painel após a confirmação do administrador.

## Fluxos implementados

- **Abertura:** destaques cadastrados, ordenados pelo campo “Ordem no carrossel”, com preload, duas camadas de imagem e transição de 850 ms. Novas trocas são iniciadas a cada 5 segundos; troca manual reinicia esse intervalo. Hover, foco, aba oculta e botão de pausa interrompem a reprodução. Movimento reduzido desativa reprodução automática e animações; setas e indicadores continuam disponíveis. Zero destaques usa uma imagem institucional aprovada ou marcador neutro; um destaque não inicia temporizador.
- **Estoque:** pesquisa de título, marca, modelo e versão; filtros de marca, combustível, câmbio, intervalo de preço e ano; ordenação por preço, ano e cadastro; nove anúncios por página. Filtros funcionam sem JavaScript.
- **Detalhe:** identificador numérico, galeria com miniaturas, setas, gesto de deslizar, teclado, visualização ampliada em diálogo, dados completos, características, descrição, WhatsApp e veículos relacionados.
- **Painel:** indicadores de cadastrados/disponíveis/reservados/vendidos/ocultos/destaques; pesquisa e paginação administrativa; CRUD, ordem dos destaques e histórico. Disponíveis e reservados ficam públicos; vendidos e ocultos retornam 404 no site e saem do carrossel.
- **Fotos:** lotes de até 12 imagens por veículo, 5 MB por arquivo e 16 megapixels. Prévia no navegador com fila: adicione seleções sucessivas, reordene ou remova arquivos antes de enviar. GD valida/decodifica e aplica orientação EXIF quando disponível. Fotografias de veículos são regravadas em WebP em canvas **16:9**, com até **1920 × 1080 px** e miniatura de até **480 × 270 px**. A foto inteira é encaixada em fundo neutro, sem esticar ou cortar; imagens pequenas não são ampliadas na conversão. Logos e imagens institucionais mantêm proporção e transparência, com lado maior de até 1800 px. Fotos legadas não são regravadas: são apenas exibidas em molduras 16:9. Arquivos antigos sem miniatura continuam exibidos pela imagem principal.
- **Integridade:** alterações na galeria bloqueiam o veículo durante a transação. Um arquivo inválido desfaz o lote e remove as novas imagens. Substituição e exclusão removem os arquivos antigos após confirmar o banco. Falhas de remoção são registradas para manutenção.
- **Loja:** nome, logo, telefone, WhatsApp, endereço, horários, Instagram, texto institucional, texto e imagem de abertura, título e descrição SEO. A logo original pode ser restaurada sem modificar seu arquivo.

As fotos antigas em `assets/carro-*.jpg` são demonstrações históricas. Não são associadas automaticamente a anúncios. Cadastre somente veículos e fotografias autorizados pela loja.

## Segurança aplicada

PDO com parâmetros vinculados, escape de HTML, CSRF em todas as alterações, autorização administrativa, cookies HttpOnly/SameSite e Secure quando `local_http=false`, renovação de sessão no login, expiração após 30 minutos de inatividade e invalidação das outras sessões após trocar a senha. Senhas usam `password_hash`/`password_verify`, com bcrypt de custo mínimo 12, estável entre PHP 8.2 e 8.4 e sem rebaixar hashes de custo superior; o limite de 72 bytes evita truncamento silencioso pelo bcrypt.

O login contabiliza tentativas por IP e por hash do e-mail, com limites de 8 por IP e 20 por conta em 15 minutos. A checagem é serializada no banco para evitar ultrapassar limites com requisições concorrentes. Login correto não apaga tentativas do IP; mensagens de falha não distinguem contas existentes. O proxy da hospedagem deve preservar limites também na borda; o aplicativo não confia em `X-Forwarded-For` enviado pelo visitante.

Uploads têm nomes aleatórios, MIME e dimensões conferidos e conteúdo reprocessado. Caminhos de remoção aceitam apenas nomes gerados pelo sistema. `.htaccess` bloqueia arquivos privados, testes, listagem de diretórios e execução de scripts na pasta de uploads. CSP não depende de scripts ou estilos inline; JSON usa nonce e escape seguro. Erros internos não são exibidos ao visitante; logs de atividade não contêm senhas ou tokens.

Antes de hospedar, configure HTTPS, `local_http=false`, usuário de banco com privilégios mínimos de execução, backups testados, atualizações e regras equivalentes caso use outro servidor. O usuário de migração precisa de permissões DDL; o usuário web precisa apenas das operações de dados. Verifique HTTP **403** em `/app/config.php`, `/database.sql` e `/tests/integration.php`. Testes locais não substituem auditoria independente ou validação da hospedagem final.

## Testes

### Verificação rápida

```sh
php tests/smoke.php
```

Verifica sintaxe de todos os arquivos PHP e presença dos arquivos necessários. Preserva código de saída diferente de zero em falhas.

### Integração HTTP + MySQL

Use **banco descartável vazio terminado em `_test`**, com o esquema e as migrações aplicados. Inicie uma instância Apache separada apontando para esse banco por `MK3_DB_NAME`, por exemplo na porta 8081. Ela precisa carregar os `.htaccess` do projeto e ter **`MK3_TEST_MODE=1`**, além de `MK3_DB_NAME` apontando para o banco descartável. Esse modo emite uma confirmação somente para nomes de banco terminados em `_test`. As suítes conferem essa confirmação antes de escrever, e recusam o servidor principal. Nunca habilite o modo de testes na instância da loja.

```sh
MK3_DB_NAME=mk3_test php app/migrate.php
MK3_DB_NAME=mk3_test MK3_TEST_BASE_URL=http://127.0.0.1:8081 php tests/integration.php
```

No Windows, defina essas variáveis no terminal antes de chamar `php`. Importe o SQL trocando o nome de banco para `mk3_test` apenas na cópia usada para testes. Servidor e teste precisam usar o mesmo banco descartável.

A suíte executa cadastro inicial, login, CSRF, CRUD, XSS, SQL injection, filtros, paginação, reservas, upload real, rollback de lote, isolamento de fotos por veículo, capa, reordenação, substituição, exclusão, configurações, troca de senha e rate limiting. Recusa banco não vazio e limpa os próprios registros e arquivos ao terminar.

### Navegador

Opcional para desenvolvimento: Node.js, Playwright e Chromium. Essas ferramentas não são dependências da aplicação. Instale-as em diretório de ferramentas separado, sem Composer e sem alterar o runtime PHP.

```sh
MK3_TEST_DB_NAME=mk3_test MK3_TEST_BASE_URL=http://127.0.0.1:8081 CHROMIUM_PATH=/caminho/do/chromium node tests/browser.cjs
```

Aponte para o servidor de testes com banco vazio. A suíte cria administrador e veículos temporários pelo painel, testa zero/um/dois destaques, troca automática/manual, reinício, pausas, galeria, zoom, prévia de fotos, menu móvel, desktop/mobile/tablet e movimento reduzido. Remove seus veículos ao terminar. **Recrie o banco descartável após a suíte de navegador**, pois o administrador e seu histórico ficam nesse banco. `MK3_SCREENSHOTS_DIR` salva capturas fora do repositório, se definido.

A suíte verifica erros JavaScript/CSP e overflow horizontal. Para alterações visuais, revise também as capturas. Homologação dos dados reais e configuração HTTPS da hospedagem devem ser feitas antes da entrega ao cliente.

## Compatibilidade e trabalho visual em andamento

A branch `feature/mk3-redesign-php82` corrige duas expressões que eram exclusivas do PHP 8.4, no processamento de imagens e no histórico. `.prettierrc.json` fixa a versão-alvo em PHP 8.2 para evitar que um formatador volte a gerar sintaxe incompatível. A aplicação continua sem dependências de Composer.

O redesign visual final do site e do painel aguarda as referências a serem fornecidas pelo cliente. As correções de compatibilidade e a galeria 16:9 não alteram os dados da loja nem reprocessam fotos existentes.
