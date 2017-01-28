<?php

class VotingElement {
	private $text;
	private $votes;
	private $id;

	public function __construct($vtext, $id, $vvotes=array()) {
		$this->text = $vtext;
		$this->votes = array();
		$this->id = $id;

		foreach ($vvotes as $vote)
		{
			$this->votes[$vote] = NULL;
		}
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

		$this->votes[strtolower($name)] = NULL;
	}

	public function hasVoted($name) {
		return array_key_exists($name, $this->votes);
	}

	public function getId() {
		return $this->id;
	}
}

class VotingSystem {
	private $voteElements;
	private $allowMultiVote;
	private $dataPath;
	private $pollText;

	const formatSplitter = ':';

	private function savedata() {
		// wait for writing availability
		while (file_exists($this->dataPath.'.lock'));

		touch($this->dataPath.'.lock');
		$f = fopen($this->dataPath, 'w');

		$contents = $this->pollText . "\n";
		foreach ($this->voteElements as $element)
		{
			$voters = '';
			if ($element->voteCount() > 0)
			{
				// $voters = implode(',', $element->getVotes());
				foreach ($element->getVotes() as $name => $v)
				{
					$voters .= $name . ',';
				}

				$voters = substr($voters, 0, strlen($voters)-1);
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

			if (count($contents) > 0)
			{
				$this->pollText = $contents[0];
				unset($contents[0]);

				$id = 0;
				foreach ($contents as $el)
				{
					if (trim($el) != '')
					{
						$element = explode(VotingSystem::formatSplitter, $el);
						$voters = array();
						if (trim($element[0]) != "")
							$voters = explode(',', $element[0]);
						array_push($this->voteElements, new VotingElement($element[1], $id, $voters));

						$id++;
					}
				}
			}
		}
		else
		{
			touch($dataFile);
		}

		$this->dataPath = $dataFile;
	}

	public function getText() {
		return $this->pollText;
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

		if (array_key_exists($elId, $this->voteElements))
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
		return new VotingSystem(realpath(ABSPATH.'data/'.$name.'.poll'));
	}
};