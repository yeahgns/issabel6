{* Tema Bradial Voz: barra lateral, barra superior e abertura da área de conteúdo.
   As tags abertas aqui são fechadas no index.tpl. *}
{assign var=BV value="`$WEBPATH`themes/`$THEMENAME`/assets"}
{literal}
<script type='text/javascript'>
var themeName='bradial';
$(document).ready(function(){
    LblRegister = '{/literal}{$Register}{literal}';
    // Menu de exportação das listagens (_list.tpl)
    $("#neo-table-button-download-right, #export_button").on('click', function () {
        var $m = $("#subMenuExport");
        if ($m.hasClass("neo-display-none")) {
            var p = $('#export_button').position();
            $m.css({top: (p.top + 38) + "px", left: p.left + "px"}).removeClass("neo-display-none");
        } else {
            $m.addClass("neo-display-none");
        }
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#export_button, #neo-table-button-download-right, #subMenuExport').length) {
            $("#subMenuExport").addClass("neo-display-none");
        }
    });
});
function removeNeoDisplayOnMouseOut(ref){ $(ref).find('div').addClass('neo-display-none'); }
function removeNeoDisplayOnMouseOver(ref){ $(ref).find('div').removeClass('neo-display-none'); }
</script>
{/literal}

<input type="hidden" id="lblRegisterCm"   value="{$lblRegisterCm}" />
<input type="hidden" id="lblRegisteredCm" value="{$lblRegisteredCm}" />
<input type="hidden" id="userMenuColor" value="{$MENU_COLOR}" />
<input type="hidden" id="toolTip_addBookmark" value="{$ADD_BOOKMARK}" />
<input type="hidden" id="toolTip_removeBookmark" value="{$REMOVE_BOOKMARK}" />
<input type="hidden" id="toolTip_addingBookmark" value="{$ADDING_BOOKMARK}" />
<input type="hidden" id="toolTip_removingBookmark" value="{$REMOVING_BOOKMARK}" />
<input type="hidden" id="toolTip_hideTab" value="{$HIDE_IZQTAB}" />
<input type="hidden" id="toolTip_showTab" value="{$SHOW_IZQTAB}" />
<input type="hidden" id="toolTip_hidingTab" value="{$HIDING_IZQTAB}" />
<input type="hidden" id="toolTip_showingTab" value="{$SHOWING_IZQTAB}" />
<input type="hidden" id="amount_char_label" value="{$AMOUNT_CHARACTERS}" />
<input type="hidden" id="save_note_label" value="{$MSG_SAVE_NOTE}" />
<input type="hidden" id="get_note_label" value="{$MSG_GET_NOTE}" />
<input type="hidden" id="issabel_theme_name" value="{$THEMENAME}" />
<input type="hidden" id="lbl_no_description" value="{$LBL_NO_STICKY}" />

<aside class="bv-sidebar" id="bv-sidebar" aria-label="Menu">
    <div class="bv-brand">
        <a href="index.php" title="Bradial Voz">
            <img class="bv-logo-full" src="{$BV}/img/bradial-voz.png" alt="Bradial Voz" />
            <img class="bv-logo-icon" src="{$BV}/img/bradial-voz-icone.png" alt="" />
        </a>
    </div>

    <form class="bv-search" method="get" action="" role="search">
        <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#search"></use></svg>
        <label class="bv-sr" for="search_module_issabel">Buscar módulo</label>
        <input type="text" id="search_module_issabel" name="search_module_issabel" placeholder="Buscar módulo" autocomplete="off" />
    </form>

    <nav class="bv-nav">
    {foreach from=$arrMainMenu key=idMenu item=menu}
        <div class="bv-nav-group">
        {if $menu.children}
            <div class="bv-nav-label">{$menu.Name}</div>
            {foreach from=$menu.children key=idSubMenu item=subMenu}
                {if $subMenu.children}
                    <div class="bv-nav-branch{if $idSubMenu eq $idSubMenuSelected} is-open has-active{/if}">
                        <button type="button" class="bv-nav-item" title="{$subMenu.Name|escape}" aria-expanded="{if $idSubMenu eq $idSubMenuSelected}true{else}false{/if}">
                            <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#{$subMenu.bicon}"></use></svg>
                            <span>{$subMenu.Name}</span>
                            <svg class="bv-i bv-chev" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#chevron-down"></use></svg>
                        </button>
                        <div class="bv-nav-sub">
                        {foreach from=$subMenu.children key=idSubMenu2 item=subMenu2}
                            {if $subMenu2.Type eq "popup"}
                                <a class="bv-nav-subitem" href="{$subMenu2.Link}" target="{$idSubMenu2}">{$subMenu2.Name}</a>
                            {else}
                                <a class="bv-nav-subitem{if $idSubMenu2 eq $idSubMenu2Selected} is-active{/if}" href="index.php?menu={$idSubMenu2}"{if $idSubMenu2 eq $idSubMenu2Selected} aria-current="page"{/if}>{$subMenu2.Name}</a>
                            {/if}
                        {/foreach}
                        </div>
                    </div>
                {else}
                    {if $subMenu.Type eq "popup"}
                        <a class="bv-nav-item" href="{$subMenu.Link}" target="{$idSubMenu}" title="{$subMenu.Name|escape}">
                    {else}
                        <a class="bv-nav-item{if $idSubMenu eq $idSubMenuSelected} is-active{/if}" href="index.php?menu={$idSubMenu}" title="{$subMenu.Name|escape}"{if $idSubMenu eq $idSubMenuSelected} aria-current="page"{/if}>
                    {/if}
                        <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#{$subMenu.bicon}"></use></svg>
                        <span>{$subMenu.Name}</span>
                    </a>
                {/if}
            {/foreach}
        {else}
            <a class="bv-nav-item{if $idMenu eq $idMainMenuSelected} is-active{/if}" href="index.php?menu={$idMenu}" title="{$menu.Name|escape}">
                <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#{$menu.bicon}"></use></svg>
                <span>{$menu.Name}</span>
            </a>
        {/if}
        </div>
    {/foreach}
    </nav>

    <div class="bv-user">
        <div class="bv-avatar" aria-hidden="true">{$USER_INITIALS}</div>
        <div class="bv-user-text">
            <strong>{$USER_LOGIN|escape}</strong>
            <span>{$SERVER_NAME|escape}</span>
        </div>
        <a href="#" class="bv-iconbtn setadminpassword" title="{$CHANGE_PASSWORD}" aria-label="{$CHANGE_PASSWORD}">
            <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#key-round"></use></svg>
        </a>
        <a href="index.php?logout=yes" class="bv-iconbtn" title="Sair" aria-label="Sair">
            <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#log-out"></use></svg>
        </a>
    </div>
</aside>
<div class="bv-backdrop"></div>

<div class="bv-main">
    <header class="bv-topbar">
        <button type="button" class="bv-iconbtn bv-menu-toggle" title="Recolher ou expandir o menu" aria-label="Recolher ou expandir o menu" aria-controls="bv-sidebar" aria-expanded="true">
            <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#panel-left"></use></svg>
        </button>

        <nav class="bv-crumb" aria-label="Você está em">
        {foreach from=$BREADCRUMB item=value name=crumb}
            {if $smarty.foreach.crumb.last}
                <span aria-current="page">{$value}</span>
            {else}
                <span>{$value}</span>
                <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#chevron-right"></use></svg>
            {/if}
        {/foreach}
        </nav>

        <div class="bv-actions">
            {if $ISSABEL_PANELS}
            <button type="button" class="bv-iconbtn" id="header_open_sidebar" title="{$LBL_ISSABEL_PANELS_SIDEBAR}" aria-label="{$LBL_ISSABEL_PANELS_SIDEBAR}">
                <svg class="bv-i" aria-hidden="true"><use href="{$BV}/icons/sprite.svg#panel-right"></use></svg>
            </button>
            {/if}
        </div>
    </header>

    <!-- conteúdo do módulo -->
    <div id="neo-contentbox" class="bv-content">
        <div id="neo-contentbox-maincolumn">
            <input type="hidden" id="issabel_framework_module_id" value="{if empty($idSubMenu2Selected)}{$idSubMenuSelected}{else}{$idSubMenu2Selected}{/if}" />
            <input type="hidden" id="issabel_framework_webCommon" value="{$WEBCOMMON}" />
            <div class="neo-module-content{if !$BV_CUSTOM_MODULE} bv-native{/if}">
