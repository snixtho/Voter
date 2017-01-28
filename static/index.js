var RequestResponse = {
	SUCCESS: 0,
	ERROR_INVALID_ACTION: 1,
	ERROR_INVALID_ARGS: 2,
	ERROR_UNKNOWN: 3
};

var LoginResponse = {
	LOGIN_SUCCESS: 1000,
	INVALID_USERPASS: 1001,
	SESSIONKEY_CORRECT: 1002,
	SESSIONKEY_INVALID: 1003
};

class Pages {
	static LoadPage(name, callback) {
		if (Pages.pagecache[name] == undefined)
		{
			$.get('static/pages/' + name + '.html', function(data) {
				Pages.pagecache[name] = data;
				callback(data);
			});
		}

		callback(Pages.pagecache[name]);
	}

	static Transition(fromPage, toPage, completed) {
		if (fromPage == null)
		{
			$('#loading_view').css('display', '');
			Pages.LoadPage(toPage, function(pageData) {
				let page = $.parseHTML(pageData);

				$('#loading_view').css('display', 'none');
				$(page).fadeOut(0);
				$('maincontainer').append(page);
				$(page).fadeIn(200);

				if (completed != undefined)
				{
					completed();
				}
			})
		}
		else
		{
			$('#page_' + fromPage).fadeOut(200, function() {
				$(this).remove();
				$('#loading_view').css('display', '');
				Pages.LoadPage(toPage, function(pageData) {
					let page = $.parseHTML(pageData);

					$('#loading_view').css('display', 'none');
					$(page).fadeOut(0);
					$('maincontainer').append(page);
					$(page).fadeIn(200);
					
					if (completed != undefined)
					{
						completed();
					}
				})
			});
		}
	}
};

Pages.pagecache = {};

class Auth {
	static MatchSession(user, sesskey, callback) {
		$.post('handler.php?action=matchsession', {
			username: user,
			sessionkey: sesskey,
			universe: 'main'
		}, function(data) {
			if (data.code == LoginResponse.SESSIONKEY_CORRECT)
			{
				callback(true);
			}
			else
			{
				callback(false);
			}

		}).fail(function() {
			callback(false);
		});
	}
};

class RealTimeForm extends HTMLElement {
	constructor() {
		super();

		this.addEventListener('keydown', this.event_keydown);

		this.inputs = this.getElementsByTagName('realtime-input');

		let submits = this.getElementsByClassName('rt-submit');
		for (let i = 0; i < submits.length; i++)
		{
			submits[i].addEventListener('click', this.form_submit);
		}
	}

	event_keydown(e) {
		if (e.keyCode == 13 && e.target.type != 'submit')
		{
			this.form_submit(this);
		}
	}

	form_submit(e) {throw "Form submit not implemented";}
}

class RealTimeLoginForm extends RealTimeForm {
	constructor() {
		super();
	}

	form_submit(e) {
		let status = $(this).find('.rt-status');
		status.attr('src', 'static/img/loading.gif');
		status.attr('width', '16');
		status.removeAttr('style');

		let user = $('input[name="login_username"]').val();
		let pass = $('input[name="login_password"]').val();

		$.post('handler.php?action=login', {
			username: user,
			password: pass,
			universe: 'main'
		}, function(data) {
			if (data.code == LoginResponse.LOGIN_SUCCESS)
			{
				status.attr('src', 'static/img/valid.png');
				status.attr('title', 'Login succeeded!');
				status.attr('width', '16');

				Cookies.set('username', user);
				Cookies.set('sessionkey', data.sessionkey);

				Pages.Transition('login', 'welcome', function(){
					pollList.refresh();
					$('#welcome-header').text('Welcome, ' + Cookies.get('username'));
				});
			}
			else
			{
				status.attr('src', 'static/img/error.png');
				status.attr('width', '12');

				if (data.code == LoginResponse.INVALID_USERPASS)
				{
					status.attr('title', 'Invalid username/password combination.');
				}
				else
				{
					status.attr('title', 'Login failed (Unknown reason).');
				}
			}
		}).fail(function() {
			status.attr('src', 'static/img/error.png');
			status.attr('width', '12');
			status.attr('title', 'Server error, try again later.');
		});
	}
}

class PollItem {
	constructor(text, name, alt) {
		this.text = text;
		this.name = name;

		this.element = $(document.createElement('li'));
		this.element.pollitem = this;
		this.element.text(text);
		var pollitem = this;
		this.element.on('click', function(e){
			pollitem.openPoll();
		});
		$('#welcome-poll-list').append(this.element);

		if (alt)
		{
			this.element.addClass('one');
		}
		else
		{
			this.element.addClass('two');
		}
	}

	getName() {
		return this.name;
	}

	getText() {
		return this.text;
	}

	getElement() {
		return this.element;
	}

	removeMe() {
		this.element.remove();
	}

	openPoll() {
		var pi = this;
		Pages.Transition('welcome', 'votingpanel', function(){
			pollManager = new Poll(pi.getName(), false);

			$('#results-btn').on('click', function(){
				pollManager.clear();
				pollManager = new Poll(pi.getName(), !pollManager.isShowingResults());
				$('#results-btn').text((pollManager.isShowingResults() ? 'Voting >>' : 'Results >>'));
			});
		});
	}
};

class PollList {
	constructor() {
		this.elements = []
	}

	addElement(text, name, alt) {
		this.elements.push(new PollItem(text, name, alt));
	}

	clear() {
		for (var i = 0; i < this.elements.length; i++)
		{
			this.elements[i].removeMe();
		}

		this.elements = [];
	}

	refresh() {
		$.post('handler.php?action=getpolllist', {
			username: Cookies.get('username'),
			sessionkey: Cookies.get('sessionkey'),
			universe: 'main'
		}, function(data) {
			if (data.code == RequestResponse.SUCCESS)
			{
				pollList.clear();
				
				let alt = true;
				for (var i = 0; i < data.polls.length; i++)
				{
					if (data.polls[i].text.trim() == "")
					{
						data.polls[i].text = data.polls[i].name;
					}

					pollList.addElement(data.polls[i].text, data.polls[i].name, alt);
					alt = !alt;
				}
			}
			else
			{
				alert('Could not refresh (CODE: ' + data.code + '). If this occurs often or more than one time, contact the admin.');
			}
		}).fail(function(){
			alert('Refresh failed (Server error), try again later.');
		});
	}
};
var pollList = new PollList();

class Poll {
	constructor(pollname, results, callback) {
		this.htmlTable = $('#votingpanel-entries-t');
		this.elements = []
		this.pollname = pollname;
		this.showResults = results;

		var pollObj = this;

		$.post('handler.php?action=getpoll', {
			username: Cookies.get('username'),
			sessionkey: Cookies.get('sessionkey'),
			universe: 'main',
			pollname: pollname,
			results: results
		}, function(data){
			if (data.code == RequestResponse.SUCCESS)
			{
				let alt = false;
				for (var i = 0; i < data.elements.length; i++)
				{
					let el = data.elements[i];
					if (results)
					{
						var htmlObj = $.parseHTML('<tr class="votingpanel-entry ' + (alt ? 'one' : 'two') + '">\
								<td class="votingpanel-entrytext">' + el.text + '</td>\
								<td class="votingpanel-votebtn">' + el.votecount.toString() + '</button></td>\
							</tr>');
					}
					else
					{
						var htmlObj = $.parseHTML('<tr class="votingpanel-entry ' + (alt ? 'one' : 'two') + '">\
								<td class="votingpanel-entrytext">' + el.text + '</td>\
								<td class="votingpanel-votebtn"><button onClick="pollManager.voteFor(' + i.toString() + ', this);"' + (el.hasvoted ? 'disabled="disabled"' : "") + '>VOTE</button></td>\
							</tr>');
					}

					pollObj.htmlTable.append(htmlObj);
					pollObj.elements[el.id] = htmlObj;
					alt = !alt;
				}

				if (callback)
				{
					callback(true);
				}
			}
			else
			{
				if (callback)
				{
					callback(false);
				}

				alert('Could not load poll (CODE: ' + data.code + '). If this occurs often or more than one time, contact the admin.');
			}
		}).fail(function(){
			alert('Poll loading failed (Server error), try again later.');
		});
	}

	isShowingResults() {
		return this.showResults;
	}

	clear() {
		for (var i = 0; i < this.elements.length; i++)
		{
			$(this.elements[i]).remove();
		}

		this.elements = [];
	}

	voteFor(id, disable) {
		if (id < 0 || this.elements[id] == undefined)
		{
			return;
		}

		if (disable != undefined)
		{
			$(disable).attr('disabled', 'disabled');
		}

		var p = this;
		$.post('handler.php?action=vote', {
			username: Cookies.get('username'),
			sessionkey: Cookies.get('sessionkey'),
			universe: 'main',
			pollname: p.pollname,
			elementid: id
		}, function(data){
			if (data.code != RequestResponse.SUCCESS)
			{
				alert('Could not vote (CODE: ' + data.code + '). If this occurs often or more than one time, contact the admin.');
			}
		}).fail(function(){
			alert('Vote failed (Server error), try again later.');
		});
	}
};
var pollManager = null;

customElements.define('realtime-login', RealTimeLoginForm);

if (Cookies.get('username') == undefined || Cookies.get('sessionkey') == undefined)
{
	Pages.Transition(null, 'login');
}
else
{
	Auth.MatchSession(Cookies.get('username'), Cookies.get('sessionkey'), function(correct) {
		if (correct)
		{
			Pages.Transition(null, 'welcome', function(){
				pollList.refresh();
				$('#welcome-header').text('Welcome, ' + Cookies.get('username'));
			});
		}
		else
		{
			Pages.Transition(null, 'login');
		}
	});
}
