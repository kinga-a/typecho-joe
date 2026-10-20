document.addEventListener('DOMContentLoaded', () => {
	$(function () {
		/* Typecho 1.3 起，后台菜单改为顶栏 <menu><li><a>父项</a><menu><li>子项</li></menu></li></menu>，
		   不再渲染旧的 #typecho-nav-list。这里从顶栏菜单提取四个分组（控制台/撰写/管理/设置），
		   重建 WangStyle 的滑出式侧边栏。 */
		var $topMenu = $('.typecho-head-nav menu');
		var $groups = $topMenu.children('li').has('menu');

		function groupHtml(idx) {
			var $g = $groups.eq(idx);
			return $g.length ? ($g.children('menu').html() || '') : '';
		}
		/* e=控制台  a=撰写  i=管理  l=设置 */
		var e = groupHtml(0),
			a = groupHtml(1),
			i = groupHtml(2),
			l = groupHtml(3);

		/* 侧边栏用户信息 */
		var r = '<div class="user-info"><a href="' + UserLink + '"><img src="' + UserPic + 's=100" /></a><p>Hi，' + UserName + '</p></div>';
		if ('administrator' == UserGroup) {
			var o = '<div class="user-nav"><ul>'
				+ '<li><a href="' + AdminLink + 'index.php"><i class="zm zm-zu"></i>主页<i></i></a></li>'
				+ '<li class="menu-li"><a href="javascript:;"><i class="zm zm-quanjushuxing"></i>全局<i class="zm zm-sanJiaoBottom"></i></a><ul class="menu-ul">' + e + '</ul></li>'
				+ '<li class="menu-li"><a href="javascript:;"><i class="zm zm-zhuomiankuaijiefangshi"></i>撰写<i class="zm zm-sanJiaoBottom"></i></a><ul class="menu-ul">' + a + '</ul></li>'
				+ '<li class="menu-li"><a href="javascript:;"><i class="zm zm-leibie"></i>管理<i class="zm zm-sanJiaoBottom"></i></a><ul class="menu-ul">' + i + '</ul></li>'
				+ '<li class="menu-li"><a href="javascript:;"><i class="zm zm-shezhi"></i>设置<i class="zm zm-sanJiaoBottom"></i></a><ul class="menu-ul">' + l + '</ul></li>'
				+ '</ul></div>';
		} else if ('editor' == UserGroup || 'contributor' == UserGroup) {
			var o = '<div class="user-nav"><ul>'
				+ '<li><a href="' + AdminLink + 'index.php"><i class="zm zm-zu"></i>主页</a></li>'
				+ '<li><a href="' + AdminLink + 'profile.php"><i class="zm zm-shezhi"></i>个人设置</a></li>'
				+ '<li><a href="' + AdminLink + 'write-post.php"><i class="zm zm-bianji"></i>撰写文章</a></li>'
				+ '<li><a href="' + AdminLink + 'manage-posts.php"><i class="zm zm-leibie"></i>我的文章</a></li>'
				+ '<li><a href="' + AdminLink + 'manage-comments.php"><i class="zm zm-quanjushuxing"></i>评论管理</a></li>'
				+ '</ul></div>';
		} else {
			var o = '<div class="user-nav"><ul>'
				+ '<li><a href="' + AdminLink + 'index.php"><i class="zm zm-zu"></i>主页</a></li>'
				+ '<li><a href="' + AdminLink + 'profile.php"><i class="zm zm-shezhi"></i>个人设置</a></li>'
				+ '</ul></div>';
		}

		if ('' != UserGroup) {
			/* 1.3 没有 #typecho-nav-list 时，动态创建一个挂到 body */
			var $nav = $('#typecho-nav-list');
			if (!$nav.length) {
				$nav = $('<nav id="typecho-nav-list"></nav>').appendTo('body');
			}
			$nav.addClass('ws-sidebar').html(r + o);
			if (!$('.proup').length) {
				$('body').append('<div class="proup"></div>');
			}

			var $operate = $('.typecho-head-nav .operate').eq(0);
			if ($operate.length) {
				$operate.prepend('<a id="dark-mode" href="javascript:;"><i class="zm zm-heiye"></i></a>');
				$operate.prepend('<a id="tonav" href="javascript:;" title="菜单"><i class="zm zm-caidan"></i></a>');
			}
		}

		/* 用事件委托绑定开关（按钮是动态注入的，避免直接绑定失效） */
		$('body').on('click', '#tonav', function (event) {
			event.preventDefault();
			event.stopPropagation();
			$('#typecho-nav-list').toggleClass('ws-open');
			$('.proup').toggleClass('ws-open');
			return false;
		});
		$('body').on('click', '.proup', function () {
			$('#typecho-nav-list').removeClass('ws-open');
			$(this).removeClass('ws-open');
		});
		$(document).on('click.wsSidebar', function (event) {
			var $nav = $('#typecho-nav-list');
			if ($nav.hasClass('ws-open') && !$nav.is(event.target) && $nav.has(event.target).length === 0 &&
				!$(event.target).closest('#tonav').length) {
				$nav.removeClass('ws-open');
				$('.proup').removeClass('ws-open');
			}
		});

		if ('' != UserGroup && MenuTitle == '个人设置') {
			$('.profile-avatar:eq(0)').attr('src', UserPic + 's=640');
			$('.typecho-page-main div:eq(0)').attr('style', 'text-align:center');
		}

		/* 展开当前所在分组 */
		var $focusLi = $('#typecho-nav-list li.focus');
		$focusLi.closest('.menu-ul').show();
		$('body').on('click', '.menu-li', function () {
			$(this).find('.menu-ul').is(':hidden') && $('.menu-ul').not($(this).find('.menu-ul')).hide(200);
			$(this).find('.menu-ul').slideToggle(200);
		});

		/* 编辑器全屏 */
		$('body').on('click', '#wmd-fullscreen-button', function () {
			$('.main').addClass('main-in');
		});
		$('body').on('click', '#wmd-exit-fullscreen-button', function () {
			$('.main').removeClass('main-in');
		});

		/* 页面微调 */
		$('.typecho-list-table colgroup').remove();
		if ('网站概要' == MenuTitle) {
			$('.typecho-page-title h2').hide();
		}
		if ($(window).width() < 575) {
			if ('插件管理' == MenuTitle) {
				$('.typecho-table-wrap tr').find('td:eq(0)').css({ float: 'none', padding: '10px' });
				$('.typecho-list-table tr').find('td:eq(1)').attr('data-label', '描述：');
				$('.typecho-list-table tr').find('td:eq(2)').hide();
				$('.typecho-list-table tr').find('td:eq(4)').css({ display: 'block', 'font-size': '1rem' });
			}
			if ('管理文章' == MenuTitle || '管理独立页面' == MenuTitle) {
				$('.typecho-list-table tr').find('td:eq(1)').attr('data-label', '评论：');
			}
			if ('管理评论' == MenuTitle) {
				$('.typecho-list-table td').find('td:eq(2)').css('display', 'block').attr('data-label', '评论者：');
				$('.typecho-list-table tr').find('td:eq(3)').css('display', 'block').attr('data-label', '发表于：');
			}
			if ('管理分类' == MenuTitle) {
				$('.typecho-list-table tr').find('td:eq(5)').attr('data-label', '文章数：');
			}
			if ('管理用户' == MenuTitle) {
				$('.typecho-list-table tr').find('td:eq(1)').attr('data-label', '文章数：');
				$('.typecho-list-table tr').find('td:eq(4)').css('display', 'block');
			}
		}

		/* 显示 canvas 图片 */
		$('tbody tr .comment-content p').each(function (i, item) {
			var str = $(item).html();
			if (!/\{!\{.*\}!\}/.test(str)) return;
			str = str.replace(/{!{/, '').replace(/}!}/, '');
			$(item).html('<img src="' + str + '" />');
		});

		/* 暗色模式 */
		if (localStorage.getItem('dark-mode')) {
			$('html').attr('dark-mode', 'dark-mode');
			$('#dark-mode').html('<i class="zm zm-baitian"></i>');
		} else {
			$('html').removeAttr('dark-mode');
			$('#dark-mode').html('<i class="zm zm-heiye"></i>');
		}
		$('body').on('click', '#dark-mode', function () {
			if ($('html').attr('dark-mode')) {
				$('html').removeAttr('dark-mode');
				localStorage.removeItem('dark-mode');
				$('#dark-mode').html('<i class="zm zm-heiye"></i>');
			} else {
				$('html').attr('dark-mode', 'dark-mode');
				localStorage.setItem('dark-mode', 'dark-mode');
				$('#dark-mode').html('<i class="zm zm-baitian"></i>');
			}
		});
	});
});
