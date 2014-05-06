<?php
/**
*
* @package Board Rules Extension
* @copyright (c) 2014 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbb\boardrules\operators;

use Symfony\Component\DependencyInjection\Container;

/**
* Operator for a set of rules
*/
class rule implements rule_interface
{
	/** @var Container */
	protected $phpbb_container;

	/**
	* Nestedset for board rules
	*
	* @var \phpbb\boardrules\operators\nestedset_rules
	*/
	protected $nestedset_rules;

	/**
	* Constructor
	*
	* @param Container $phpbb_container
	* @param \phpbb\boardrules\operators\nestedset_rules $nestedset_rules Nestedset object for tree functionality
	* @return \phpbb\boardrules\operators\rule
	* @access public
	*/
	public function __construct(Container $phpbb_container, \phpbb\boardrules\operators\nestedset_rules $nestedset_rules)
	{
		$this->phpbb_container = $phpbb_container;
		$this->nestedset_rules = $nestedset_rules;
	}

	/**
	* Get the rules
	*
	* @param int $language Language selection identifier; default: 0
	* @param int $parent_id Category to display rules from; default: 0
	* @return array Array of rule data entities
	* @access public
	*/
	public function get_rules($language = 0, $parent_id = 0)
	{
		$entities = array();

		// Load all rule data from the database into an array
		$rowset = $this->nestedset_rules
			->use_language($language)
			->get_rules_data($parent_id);

		// Import each rule into an entity, and store them in an array
		foreach ($rowset as $row)
		{
			$entities[] = $this->phpbb_container->get('phpbb.boardrules.entity')
				->import($row);
		}

		// Return all rule entities
		return $entities;
	}

	/**
	* Add a rule
	*
	* @param object $entity Rule entity with new data to insert
	* @param int $language Language selection identifier; default: 0
	* @param int $parent_id Category to display rules from; default: 0
	* @return rule_interface Added rule entity
	* @access public
	* @throws \phpbb\boardrules\exception\base
	*/
	public function add_rule($entity, $language = 0, $parent_id = 0)
	{
		// Insert the rule data to the database for the given language selection
		$entity->insert($language);

		// Get the newly inserted rule's identifier
		$rule_id = $entity->get_id();

		// Update the tree for the rule in the database
		$this->nestedset_rules->add_to_nestedset($rule_id);

		// If a parent id was supplied, update the rule's parent id and tree ids
		if ($parent_id)
		{
			$this->nestedset_rules->change_parent($rule_id, $parent_id);
		}

		// Reload the data to return a fresh rule entity
		return $entity->load($rule_id);
	}

	/**
	* Delete a rule
	*
	* @param int $rule_id The rule identifier to delete
	* @return null
	* @access public
	* @throws \phpbb\boardrules\exception\out_of_bounds
	*/
	public function delete_rule($rule_id)
	{
		$rule_id = (int) $rule_id;

		// Try to delete the rule or category from the database
		try
		{
			$this->nestedset_rules->delete($rule_id);
		}
		catch (\OutOfBoundsException $e)
		{
			throw new \phpbb\boardrules\exception\out_of_bounds('rule_id');
		}
	}

	/**
	* Move a rule up/down
	*
	* @param int $rule_id The rule identifier to move
	* @param string $direction The direction (up|down)
	* @param int $amount The number of places to move the rule
	* @return null
	* @access public
	* @throws \phpbb\boardrules\exception\out_of_bounds
	*/
	public function move($rule_id, $direction = 'up', $amount = 1)
	{
		$rule_id = (int) $rule_id;
		$amount = (int) $amount;

		// Try to move the rule or category up/down
		try
		{
			$this->nestedset_rules->move($rule_id, (($direction != 'up') ? -$amount : $amount));
		}
		catch (\OutOfBoundsException $e)
		{
			throw new \phpbb\boardrules\exception\out_of_bounds('rule_id');
		}
	}

	/**
	* Get a rule's parent rules (for use in breadcrumbs)
	*
	* @param int $language Language selection identifier
	* @param int $parent_id Category to display rules from
	* @return array Array of rule data for a rule's parent rules
	* @access public
	*/
	public function get_rule_parents($language, $parent_id)
	{
		$entities = array();

		// Load all parent rule data from the database into an array
		$rowset = $this->nestedset_rules
			->use_language($language)
			->get_path_data($parent_id);

		// Import each rule into an entity, and store them in an array
		foreach ($rowset as $row)
		{
			$entities[] = $this->phpbb_container->get('phpbb.boardrules.entity')
				->import($row);
		}
		return $entities;
	}
}
