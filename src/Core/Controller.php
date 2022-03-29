<?php
/**
 * @file      Page.php
 * @brief     Core\Page class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 *
 * @defgroup  Pages Pages
 * @brief     All page classes
 */

namespace App\Core;


use App\Core\Interfaces\ControllerInterface;
use App\Core\Interfaces\RequestInterface;

/**
 * @class   Page
 * @brief   Abstract Page class that specifies all basic functionality for other Pages
 *
 * @package Core
 * @ingroup Pages
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
abstract class Controller implements ControllerInterface
{

	public array $middleware = [];
	/**
	 * @var array $params Parameters added to latte template
	 */
	public array $params = [];
	/**
	 * @var string $title Page name
	 */
	protected string $title;
	/**
	 * @var string $description Page description
	 */
	protected string           $description;
	protected RequestInterface $request;

	/**
	 * Initialization function
	 *
	 * @param RequestInterface $request
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function init(RequestInterface $request) : void {
		$this->request = $request;
		$this->params['page'] = $this;
		$this->params['request'] = $request;
		$this->params['errors'] = $request->errors;
		$this->params['notices'] = $request->notices;
	}

	/**
	 * Getter method for page title
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function getTitle() : string {
		return Constants::SITE_NAME.(!empty($this->title) ? ' - '.lang($this->title, context: 'pageTitles') : '');
	}

	/**
	 * Getter method for page description
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function getDescription() : string {
		return lang($this->description, context: 'pageDescription');
	}

	protected function view(string $template) : void {
		view($template, $this->params);
	}

	/**
	 * @param mixed $data Serializable data
	 */
	protected function ajaxJson(mixed $data) : never {
		header('Content-Type: application/json');
		echo json_encode($data, JSON_THROW_ON_ERROR);
		exit();
	}
}
