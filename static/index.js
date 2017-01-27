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
			this.form_submit();
		}
	}

	form_submit(e) {throw "Submit handler not implemented.";}
}

RealTimeForm.prototype.form_submit = function(e) {
	let status = $(this).find('.rt-status');
	status.attr('src', 'static/img/loading.gif');
	status.removeAttr('style');
}

customElements.define('realtime-form', RealTimeForm);