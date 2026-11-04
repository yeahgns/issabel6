<!DOCTYPE html>
{assign var=BV value="`$WEBPATH`themes/`$THEMENAME`/assets"}
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Entrar · Bradial Voz</title>
    <link rel="icon" href="{$BV}/img/favicon.ico" />
    <link rel="stylesheet" href="{$BV}/css/tokens.css" />
    <link rel="stylesheet" href="{$BV}/css/components.css" />
    <link rel="stylesheet" href="{$BV}/css/shell.css" />
</head>
<body class="bv-body bv-login-body">
<main class="bv-login">
    <section class="bv-login-side">
        <img src="{$BV}/img/bradial-voz.png" alt="Bradial Voz" />

        <form class="bv-login-form" method="post" autocomplete="off">
            <div>
                <h1>Entrar no PABX</h1>
                <p>Use o usuário e a senha do seu servidor.</p>
            </div>

            {if !empty($LOGIN_INCORRECT)}
            <div class="bv-alert" role="alert">
                <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#circle-alert"></use></svg>
                <span>Usuário ou senha incorretos. Confira e tente de novo.</span>
            </div>
            {/if}

            <div class="bv-fields">
                <label class="bv-field">
                    <span>Usuário</span>
                    <input class="bl-input bl-input--lg" type="text" name="input_user" id="input_user" autocomplete="username" autofocus required />
                </label>
                <label class="bv-field">
                    <span>Senha</span>
                    <div class="bv-pass">
                        <input class="bl-input bl-input--lg" type="password" name="input_pass" id="input_pass" placeholder="Sua senha" autocomplete="current-password" required />
                        <button type="button" class="bv-iconbtn" id="bv-toggle-pass" data-sprite="{$BV}/icons/sprite.svg" aria-label="Mostrar senha" aria-pressed="false">
                            <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#eye"></use></svg>
                        </button>
                    </div>
                </label>
            </div>

            <button type="submit" class="bl-btn bl-btn--primary bl-btn--lg" name="submit_login" value="1">Entrar</button>
        </form>

        <div class="bv-login-foot">© {$currentyear} Bradial Telecom · {$smarty.server.SERVER_NAME|escape}</div>
    </section>

    <section class="bv-login-hero" aria-hidden="true">
        <h2><span>Conectando chamadas,</span><em>simplificando o atendimento.</em></h2>
        <p>Ramais, filas, rotas e troncos da sua operação de voz em um só lugar.</p>
        <ul class="bv-tags"><li>Ramais</li><li>Filas</li><li>Rotas</li><li>Relatórios</li></ul>
        <div class="bv-sketch-wrap">
            <div class="bv-sketch">
                <div class="bv-sk-nav">
                    <div class="bv-sk-bar is-dark"></div>
                    {section name=n loop=7}<div class="bv-sk-bar{if $smarty.section.n.index eq 1} is-blue{/if}" style="width:{70+$smarty.section.n.index*3}%"></div>{/section}
                </div>
                <div class="bv-sk-main">
                    <div class="bv-sk-bar is-dark"></div>
                    {section name=r loop=3}
                    <div class="bv-sk-row">
                        {section name=c loop=5}
                        <div class="bv-sk-card{if ($smarty.section.c.index + $smarty.section.r.index) % 3 eq 1} is-off{/if}">
                            <div class="bv-sk-bar" style="width:{50+$smarty.section.c.index*8}%"></div>
                            <div class="bv-sk-bar is-blue" style="width:32%"></div>
                        </div>
                        {/section}
                    </div>
                    {/section}
                    <div class="bv-sk-table">
                        {section name=t loop=4}<div class="bv-sk-bar" style="width:{92-$smarty.section.t.index*9}%"></div>{/section}
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
{literal}
<script>
(function () {
    var b = document.getElementById('bv-toggle-pass'), i = document.getElementById('input_pass');
    if (!b || !i) return;
    b.addEventListener('click', function () {
        var show = i.type === 'password';
        i.type = show ? 'text' : 'password';
        b.setAttribute('aria-pressed', show ? 'true' : 'false');
        b.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
        b.querySelector('use').setAttribute('href', b.getAttribute('data-sprite') + '#' + (show ? 'eye-off' : 'eye'));
        i.focus();
    });
})();
</script>
{/literal}
</body>
</html>
