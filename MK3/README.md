# MK3 Multimarcas — sistema PHP/MySQL para XAMPP

## Instalação local (sem Composer/Laravel)
1. Extraia a pasta `MK3-Multimarcas-Completo` em `C:\xampp\htdocs\mk3` (renomeie a pasta para `mk3`). Não coloque dentro da instalação Laravel anterior.
2. No painel XAMPP, inicie **Apache** e **MySQL**.
3. Entre em http://localhost/phpmyadmin, aba **Importar**, selecione `database.sql` e execute. O SQL cria o banco `mk3_multimarcas`.
4. Confira `app/config.php`: por padrão, usuário MySQL `root`, senha vazia e porta 3306. Ajuste se seu MySQL for diferente.
5. Abra http://localhost/mk3/instalar.php e crie um admin com senha forte (12+ caracteres).
6. **Exclua `instalar.php`** depois de instalar (indispensável).
7. Site: http://localhost/mk3/ — Painel: http://localhost/mk3/admin/login.php
8. Em `app/config.php` altere o número `whatsapp` caso o contato da loja mude (DDD + país, apenas dígitos).
9. Ative a extensão `gd` no `C:\xampp\php\php.ini` para uploads. Reinicie o Apache.

## Funções
- Site público responsivo escuro/vermelho, carrossel automático dos veículos marcados como destaque (4,5 s), listagem, pesquisa e ordenação, detalhe individual com galeria e WhatsApp.
- Admin: login, troca de senha, cadastro/edição/exclusão, estado disponível/vendido/oculto, preço, descrição, dados do carro, várias fotos, capa, destaques da home.
- Sem registro público de usuário. Um administrador criado no instalador; acrescente gestão multiusuário somente com autenticação/permissões adequadas.
- Fotos de demonstração antigas da V6 permanecem em `assets/`; o admin envia fotos reais para `uploads/`. Carros NÃO são inseridos automaticamente no banco (evita anunciar produtos não confirmados).

## Segurança implementada
- PDO com parâmetros vinculados; saída HTML escapada; POST com token CSRF; cookies HttpOnly/SameSite; sessão renovada no login; senhas com `password_hash`; limitação de 8 falhas por IP/15 minutos; cabeçalhos CSP, nosniff, anti-iframe, referrer policy; logs de ações; operações admin com autorização; exclusão com POST.
- Uploads com tamanho/tipo MIME/assinatura de imagem validados; reprocessamento por PHP GD; nomes aleatórios; limite de 12 imagens e 5 MB por arquivo; pasta de upload com bloqueio de execução de script em Apache. Use backups e proteção adicional da hospedagem.
- SQL, pasta app e listagem de diretórios protegidos via `.htaccess` no Apache. **Fora do Apache**, configure bloqueios equivalentes no servidor.

## ANTES DE PUBLICAR NA INTERNET
- Este pacote é uma base funcional para desenvolvimento/teste local, não passou por auditoria profissional independente e **não é garantia contra invasões**.
- Configure HTTPS válido, `local_http=false` em `app/config.php`, senhas exclusivas, permissões mínimas de banco e arquivos, rate limit na borda, WAF, atualizações do PHP/MySQL, backups automáticos testados, proteção contra ataques de força bruta distribuída e monitoramento de logs.
- Em produção, mova `app/config.php`/arquivos privados para fora do diretório público ou configure DocumentRoot dedicado e regras adequadas. Desative escrita em código da aplicação, mantenha somente `uploads/` gravável e impeça execução de PHP nessa pasta no nível do servidor.
- Restringir crawlers/IA: robots.txt é voluntário e não bloqueia agentes maliciosos. Um WAF/CDN pode filtrar bots, mas não há método infalível para distinguir/bloquear toda IA e manter o site público.
- Não publique sem autorização e confirmação dos dados/imagens da MK3. Este projeto não contém pagamentos, contas financeiras ou coleta de documentos pessoais.

## Testes
- Rode `php tests/smoke.php` para verificações estáticas simples (PHP CLI precisa estar no PATH, ou use `C:\xampp\php\php.exe tests\smoke.php`).
- Testes manuais: criar veículo, subir imagem, fazer capa, alterar destaque, testar carrossel, editar, ocultar, excluir e testar visitante anônimo tentando abrir `/admin/index.php` (deve ir ao login).
- Confira também logs do Apache/PHP, scanners OWASP ZAP e configuração de HTTPS em uma implantação de homologação com autorização.
