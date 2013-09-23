<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Token extends Sprig {	
	protected $_table = 'user_tokens';
	
	protected function _init()
	{
		$this->_fields += array(
			'id' => new Sprig_Field_Auto,
			'user_id' => new Sprig_Field_Integer,
			'user_agent' => new Sprig_Field_Char,
			'token' => new Sprig_Field_Char,
			'created' => new Sprig_Field_Timestamp(array(
				'auto_now_create' => TRUE,
			)),
			'expires' => new Sprig_Field_Timestamp,
		);
		
		// Примерно раз в 100 обращений удаляем старые токены
		if (mt_rand(1, 100) === 1)
			$this->delete_expired();
	}

	/**
	 * Handle deletion of expired token on load
	 * @param Database_Query_Builder_Select $query [optional]
	 * @param int                    	    $limit [optional]
	 * @return Sprig
	 */
	public function load(Database_Query_Builder_Select $query = NULL, $limit = 1)
	{
		parent::load($query, $limit);
		
		if ($limit === 1 AND $this->loaded() AND $this->expires < time())
		{
			$this->delete();
			$this->_loaded = FALSE;
		}
		
		return $this;
	}
	
	public function create()
	{
		// Set hash of the user agent
		$this->user_agent = sha1(Request::$user_agent);

		// Create a new token each time the token is saved
		$this->token = $this->create_token();
		
		return parent::create();
	}
	
	public function update()
	{
		// Create a new token each time the token is saved
		$this->token = $this->create_token();
		
		return parent::update();
	}
	
	/**
	 * Deletes all expired tokens.
	 *
	 * @return  void
	 */
	public function delete_expired()
	{
		// Delete all expired tokens
		DB::delete($this->_table)
			->where('expires', '<', time())
			->execute($this->_db);

		return $this;
	}
	
	/**
	 * Finds a new unique token, using a loop to make sure that the token does
	 * not already exist in the database. This could potentially become an
	 * infinite loop, but the chances of that happening are very unlikely.
	 *
	 * @return  string
	 */
	public function create_token()
	{
		while (TRUE)
		{
			// Create a random token
			$token = text::random('alnum', 32);

			// Make sure the token does not already exist
			$count = DB::select('id')
				->where('token', '=', $token)
				->from($this->_table)
				->execute($this->_db)
				->count();
			if ($count === 0)
			{
				// A unique token has been found
				return $token;
			}
		}
	}
}