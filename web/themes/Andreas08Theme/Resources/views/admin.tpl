{include file='includes/header.tpl'}

<div id="theme_navigation_bar">
    <ul class="z-clearfix">
        <li><a href="{homepage}">{gt text='Home'}</a></li>
        <li><a href="{modurl modname='Settings' type='admin' func='index'}">{gt text="Settings"}</a></li>
        <li><a href="{modurl modname='Extensions' type='admin' func='index'}">{gt text="Extensions"}</a></li>
        <li><a href="{modurl modname='Blocks' type='admin' func='index'}">{gt text="Blocks"}</a></li>
        <li><a href="{modurl modname='Users' type='admin' func='index'}">{gt text="Users"}</a></li>
        <li><a href="{modurl modname='Groups' type='admin' func='index'}">{gt text="Groups"}</a></li>
        <li><a href="{modurl modname='Permissions' type='admin' func='index'}">{gt text="Permission rules"}</a></li>
        <li><a href="{modurl modname='Theme' type='admin' func='index'}">{gt text="Themes"}</a></li>
    </ul>
</div>

{include file="body/$admin.tpl"}
{include file='includes/footer.tpl'}
