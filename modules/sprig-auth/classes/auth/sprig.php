<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Sprig Auth driver.
 *
 * @package    Sprig Auth
 * @author     Paul Banks
 */
class Auth_Sprig extends Auth {

	/* * *
		������� �������� �������� ��������� ����������������
		���� ������ ���� - ���� �������� �� �������������� � ����
	* * */
	public function logged_in($role = NULL)
	{
		$status = FALSE;

		// �������� ������� ������������ �� ������
		$user = $this->_session->get($this->_config['session_key']);
		
		if ( ! is_object($user))
		{
			// Attempt auto login
			if ($this->auto_login())
			{
				// Success, get the user back out of the _session
				$user = $this->_session->get($this->config['session_key']);
			}
		}

		if (is_object($user) AND $user instanceof Model_User AND $user->loaded())
		{
			// Everything is okay so far
			$status = FALSE;

			if ( ! empty($role))
			{
				// If role is an array
				if (is_array($role))
				{
					// Check each role
					foreach ($role as $role_iteration)
					{
						// If the user doesn't have the role
						if( $user->has_role($role_iteration))
						{
							// Set the status false and get outta here
							$status = TRUE;
							break;
						}
					}
				}
				else
				{
					// Check that the user has the given role
					$status = $user->has_role($role);
				}
			}
		}

		return $status;
	}

	/* * *
		������� �����������
	* * */
	public function _login($user, $password, $remember)
	{
		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			$user = Sprig::factory('user', Array('login' => $username))->load();
		}

		if (is_string($password))
		{
			// ������� ��� �����
			$password = $this->hash($password);
		}

		// ��������� ������� ���� Login � ������������ ������
		if ($user->has_role('login') AND $user->password === $password)
		{
			// ���� ������ ������� ��������� ����
			if ($remember === TRUE)
			{
				// Token data
				$data = array(
					'user_id'    => $user->id,
					'expires'    => time() + $this->_config['lifetime'],
					'user_agent' => sha1(Request::$user_agent),
				);

				// Create a new autologin token
				$token = Sprig::factory('token')
							->values($data)
							->create();

				// Set the autologin cookie
				Cookie::set('authautologin', $token->token, $this->_config['lifetime']);
			}

			// Finish the login
			$this->complete_login($user);

			return TRUE;
		}

		// Login failed
		return FALSE;
	}

	/**
	 * Forces a user to be logged in, without specifying a password.
	 *
	 * @param   mixed    username
	 * @return  boolean
	 */
	public function force_login()
	{
		// Make sure we have a user object
		$user = $this->get_user();

		// Mark the _session as forced, to prevent users from changing account information
		$__session['auth_forced'] = TRUE;

		// Run the standard completion
		$this->complete_login($user);
	}

	/* * *
		����������� ������������ �� ���
	* * */
	public function auto_login()
	{
		if ($token = cookie::get('authautologin'))
		{
			// Load the token and user
			$token = Sprig::factory('token', array('token' => $token))->load();
			
			if ($token->loaded())
			{
				$user = Sprig::factory('user', array('id' => $token->user_id))->load();
				if (!empty($user) && $user->loaded())
				{
					if ($token->user_agent === sha1(Request::$user_agent))
					{
						// Save the token to create a new unique token
						$token->update();

						// Set the new token
						cookie::set('authautologin', $token->token, $token->expires - time());

						// Complete the login with the found data
						$this->complete_login($user);

						// Automatic login was successful
						return TRUE;
					}

					// Token is invalid
					$token->delete();
				}
			}
		}

		return FALSE;
	}

	/* * *
		������� ���������� ������
	* * */
	public function logout($destroy = FALSE, $logout_all = FALSE)
	{
		if ($token = cookie::get('authautologin'))
		{echo 999;
			// Delete the autologin cookie to prevent re-login
			cookie::delete('authautologin');
			
			// Clear the autologin token from the database
			$token = Sprig::factory('token', array('token' => $token))->load();
			
			if ($token->loaded() AND $logout_all)
			{
				Sprig::factory('token', array('user_id' => $token->user->id))->delete();
			}
			elseif ($token->loaded())
			{
				$token->delete();
			}
		}

		return parent::logout($destroy);
	}

	/* * *
		������� ������ ������� �������� ������������
	* * */
	public function password($user)
	{
		// Make sure we have a user object
		$user = $this->get_user();

		return $user->password;
	}

	/* * *
		�������� ������ � �������� ������������
	* * */
	public function check_password($password)
	{
		$user = $this->get_user();

		if ( ! $user)
			return FALSE;

		return ($this->hash($password) === $user->password);
	}

	/* * *
		��������� ������ �� ������ �������� ������
		� ����� �����������
	* * */
	public function hash($str)
	{
		if ( ! $this->_config['hash_key'])
			throw new Kohana_Exception('�� ����� ���� ��� ����������� ������');

		return hash_hmac($this->_config['hash_method'], $str, $this->_config['hash_key']);
	}

	/* * *
		���������� ��������� ����� ����� �������� �����������
	* * */
	protected function complete_login($user)
	{
		$user->complete_login();
		return parent::complete_login($user);
	}

	/* * *
		��������� �������� ������������ �� ������ �����
		���� � ����� ������ ��� - ������� �������������� �� ���
		�������� ������ ���� ����������� � �������� Remmembed
	* * */
	public function get_user($default = NULL)
	{
		$user = parent::get_user($default);

		if ( ! $user)
		{
			// ������� ������������� �� ���
			// ���� ������ ���� ����������� � ��������� ��������� ����
			$user = $this->auto_login();
		}

		return $user;
	}

	/* * *
		������� ��������� ���������� ������
	* * */
	public static function get_randon_password($number)
	{
		$arr = array('a','b','c','d','e','f',
			'g','h','i','j','k','l',
			'm','n','o','p','r','s',
			't','u','v','x','y','z',
			'A','B','C','D','E','F',
			'G','H','I','J','K','L',
			'M','N','O','P','R','S',
			'T','U','V','X','Y','Z',
			'1','2','3','4','5','6',
			'7','8','9','0');
		// ���������� ������
		$pwd = '';
		for($i=0; $i<$number; $i++)
		{
			// ��������� ��������� ������ �������
			$index = rand(0, count($arr) - 1);
			$pwd .= $arr[$index];
		}
		
		return $pwd;
	}

} // End Auth_Sprig_Driver