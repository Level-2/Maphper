<?php 
namespace Maphper\Loader\Xml;
/*
 * Uses Dice to achieve lazy loading. Without dice it would need to load mappers/relations/data sources before they were needed.
 * or implement complex functionality already available in Dice
 * This won't even create an instance of PDO unless a database mapper is initialised
 */
class Xml {
	private $dice;
	private $relationStrings = ['many' => \Maphper\Relation::MANY, 'one' => \Maphper\Relation::MANY];

	public function __construct($xml, $dice) {
		$this->xml = ($xml instanceof SimpleXmlElement) ? $xml : simplexml_load_file($xml);
		$this->dice = $dice;
	}

	public function addLoader($tagName, DataSource $datasource) {
		foreach ($this->xml->source->$tagName as $dbXml) {
			$this->dice->addRule('$Maphper_Source_' . (string) $dbXml['name'], $datasource->load($dbXml));		
			$rules = [];
			foreach ($dbXml->relation as $relationXml) {
				$relation = new \Dice\Rule;
				$relation->instanceOf = 'Maphper\\Relation';
				$relation->substitutions['Maphper\\Maphper'] = new \Dice\Instance('$Maphper_' . (string) $relationXml->to);
				$relation->constructParams = [$this->relationStrings[(string) $relationXml->type], (string) $relationXml->localKey, (string) $relationXml->foreignKey];
				$name = '$Maphper_Relation_' . (string) $dbXml['name'] . '_' . (string) $relationXml['name'];
				$rules[(string) $relationXml['name']] = $name;
				$this->dice->addRule($name, $relation);
			}			
			$mapper = new \Dice\Rule;
			$mapper->instanceOf = 'Maphper\\Maphper';
			$mapper->substitutions['Maphper\\DataSource'] = new \Dice\Instance('$Maphper_Source_' . (string) $dbXml['name']);
			foreach ($rules as $name => $rule) $mapper->call[] = ['addRelation', [$name, new \Dice\Instance($rule)]];
			$this->dice->addRule('$Maphper_' . (string) $dbXml['name'], $mapper);
		}
	}
	
	public function getMapper($name) {
		return $this->dice->create('$Maphper_' . $name);
	}
}