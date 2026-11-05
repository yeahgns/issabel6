<!DOCTYPE html>
{assign var=BV value="`$WEBPATH`themes/`$THEMENAME`/assets"}
<html lang="{if $BV_LANG eq 'en'}en{else}pt-BR{/if}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Bradial Voz</title>
    <link rel="icon" href="{$BV}/img/favicon.ico" />
    {literal}<script>try{if(localStorage.getItem('bv-nav')==='recolhido')document.documentElement.classList.add('bv-collapsed');}catch(e){}</script>{/literal}

    {* CSS do Issabel: os módulos nativos dependem dele *}
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/css/bootstrap.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/css/neon-core.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/css/neon-theme.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/css/neon-forms.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/css/font-awesome-animation.min.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/css/custom.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/styles.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/widgets.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/help.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/header.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/content.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/applet.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/table.css" />
    <link rel="stylesheet" href="{$WEBPATH}themes/{$THEMENAME}/rightbar.css" />

    {* Bradial Voz: sempre por último *}
    <link rel="stylesheet" href="{$BV}/css/tokens.css?v=__BV_VERSION__" />
    <link rel="stylesheet" href="{$BV}/css/components.css?v=__BV_VERSION__" />
    <link rel="stylesheet" href="{$BV}/css/shell.css?v=__BV_VERSION__" />
    <link rel="stylesheet" href="{$BV}/css/native.css?v=__BV_VERSION__" />

    {$HEADER_LIBS_JQUERY}
    <script type="text/javascript" src="libs/js/base.js"></script>
    <script type="text/javascript" src="libs/js/iframe.js"></script>
    {$HEADER}
    {$HEADER_MODULES}
</head>
<body class="bv-body mainBody" {$BODYPARAMS}>
<div class="bv-shell">

    {$MENU} {* _menu.tpl: barra lateral, barra superior e abertura do conteúdo *}

                {if !empty($mb_message)}
                <div class="div_msg_errors" id="message_error" role="alert">
                    {if !empty($mb_title)}
                    <div class="div_msg_errors_title"><b>{$mb_title}</b></div>
                    {/if}
                    <div class="div_msg_errors_content">{$mb_message}</div>
                    <div class="div_msg_errors_dismiss" onclick="hide_message_error();" title="{$BVT.close}">
                        <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg?v=__BV_VERSION__#x"></use></svg>
                    </div>
                </div>
                {/if}
                {$CONTENT}
            </div><!-- neo-module-content -->
        </div><!-- neo-contentbox-maincolumn -->
    </div><!-- neo-contentbox -->

    <footer class="bv-footer">Bradial Telecom © 2019–{$currentyear}</footer>

</div><!-- bv-main -->
</div><!-- bv-shell -->

{if $ISSABEL_PANELS}
<div id="chat" class="fixed" aria-label="{$LBL_ISSABEL_PANELS_SIDEBAR|escape:html}">
    <div class="chat-inner">
        <h2 class="chat-header">
            <span id="panel-header-text">{$LBL_ISSABEL_PANELS_SIDEBAR|escape:html}</span>
            <a href="#" class="chat-close" aria-label="{$BVT.close}"><svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg?v=__BV_VERSION__#x"></use></svg></a>
        </h2>
        <div id="issabel-panels" class="panel-group joined">
            {foreach from=$ISSABEL_PANELS key=panelname item=paneldata name=issabelpanel}
            <div class="panel">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="collapse" data-parent="#issabel-panels" href="#issabel-panel-{$panelname}">
                            {if $paneldata.iconclass}<i class="{$paneldata.iconclass}"></i>
                            {elseif $paneldata.icon}<img alt="" src="{$paneldata.icon}" width="15" />
                            {/if}
                            <span>{$paneldata.title|escape:html}</span>
                        </a>
                    </h4>
                </div>
                <div id="issabel-panel-{$panelname}" class="panel-collapse collapse{if $smarty.foreach.issabelpanel.first} in{/if}">
                    <div class="panel-body">{$paneldata.content}</div>
                </div>
            </div>
            {/foreach}
        </div>
    </div>
</div>
{/if}

<script type="text/javascript" src="{$WEBPATH}themes/{$THEMENAME}/js/gsap/main-gsap.js"></script>
<script type="text/javascript" src="{$WEBPATH}themes/{$THEMENAME}/js/bootstrap.js"></script>
<script type="text/javascript" src="{$WEBPATH}themes/{$THEMENAME}/js/joinable.js"></script>
<script type="text/javascript" src="{$WEBPATH}themes/{$THEMENAME}/js/resizeable.js"></script>
<script type="text/javascript" src="{$WEBPATH}themes/{$THEMENAME}/js/neon-api.js"></script>
<script type="text/javascript" src="{$WEBPATH}themes/{$THEMENAME}/js/jquery.validate.min.js"></script>
<script type="text/javascript" src="{$WEBPATH}themes/{$THEMENAME}/js/neon-custom.js"></script>
<script type="text/javascript" src="{$BV}/js/shell.js?v=__BV_VERSION__"></script>
</body>
</html>
