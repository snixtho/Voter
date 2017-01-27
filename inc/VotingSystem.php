<?php

class VotingElement {
	private $text;
	private $votes;

	public function __construct($vtext, $vvotes=array()) {
		$this->text = $vtext;
		$this->votes = $vvotes;
	}

	public function getText() {
		return $this->text;
	}

	public function getVotes() {
		return $this->votes;
	}

	public function voteCount() {
		return count($this->votes);
	}

	public function addVote($name) {
		foreach ($this->votes as $vote)
		{
			if ($vote == $name)
				return;
		}

		array_push($this->votes, $name);
	}
}

class VotingSystem {
	private $voteElements;
	private $allowMultiVote;
	private $dataPath;

	const formatSplitter = ':';

	private function savedata() {
		// wait for writing availability
		while (file_exists($this->dataPath.'.lock'));

		touch($this->dataPath.'.lock');
		$f = fopen($this->dataPath, 'w');

		$contents = "";
		foreach ($this->voteElements as $element)
		{
			$voters = '';
			if ($element->voteCount() > 0)
			{
				$voters = implode(',', $element->getVotes());
			}

			$line = $voters . VotingSystem::formatSplitter . $element->getText() . "\n";

			$contents .= $line;
		}

		fwrite($f, $contents);
		fclose($f);
		unlink($this->dataPath.'.lock');
	}

	public function __construct($dataFile, $multiVote=true) {
		$allowMultiVote = true;
		$this->voteElements = array();

		if (file_exists($dataFile) && filesize($dataFile) > 0)
		{
			$f = fopen($dataFile, 'r');

			if ($f === false)
			{
				die('[Error] Could not open ' . $datafile . ' for reading. File exists? Check permissions?');
			}

			$contents = explode("\n", fread($f, filesize($dataFile)));
			fclose($f);

			foreach ($contents as $el)
			{
				if (trim($el) != '')
				{
					$element = explode(VotingSystem::formatSplitter, $el);
					$voters = array();
					if (trim($element[0]) != "")
						$voters = explode(',', $element[0]);
					array_push($this->voteElements, new VotingElement($element[1], $voters));
				}
			}
		}
		else
		{
			touch($dataFile);
		}

		$this->dataPath = $dataFile;
	}

	public function addVote($voterName, $elId) {
		if (!$this->allowMultiVote)
		{
			foreach ($this->voteElements as $element)
			{
				foreach ($element->getVotes() as $voter)
				{
					if ($voterName == $voter)
						return;
				}
			}
		}

		if (count($this->voteElements) < $elId)
		{
			$this->voteElements[$elId]->addVote($voterName);
			$this->savedata();
		}
	}

	public function addElement($text) {
		array_push($this->voteElements, new VotingElement($text));
		$this->savedata();
	}

	public function getElements() {
		return $this->voteElements;
	}

	public function getElementsDS() {
		$els = array();

		foreach ($this->voteElements as $element)
		{
			array_push($els, array($element->voteCount(), $element->getText()));
		}

		return $els;
	}

	public function getElementsDSSorted($asc=false) {
		$els = $this->getElementsDS();

		if ($asc)
		{
			usort($els, function($a, $b) {
				return $a[0] > $b[0];
			});
		}
		else
		{
			usort($els, function($a, $b) {
				return $a[0] < $b[0];
			});
		}
		

		return $els;
	}

	public function setAllowMultiVoting($allow=true) {
		$this->allowMultiVote = $allow;
	}
};


class VotingSystemFactory {
	public static function OpenPoll($name) {
		return new VotingSystem(ABSPATH.'data/'.$name.'.poll');
	}
};