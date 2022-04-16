<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Questionnaire\User;

class QuestionnaireHelper
{

	/**
	 * @return void
	 */
	public static function dontShowQuestionnaire() : void {
		setcookie('dont_show_questionnaire', true, time() + 3600 * 24 * 365, '/'); // Expire in one year
	}

	/**
	 * @return void
	 */
	public static function showQuestionnaireLater() : void {
		setcookie('dont_show_questionnaire', true, time() + 3600 * 24, '/'); // Expire in one day
	}

	/**
	 * Check if the questionnaire should be shown to the user
	 *
	 * Checks the presence of a "dont_show_questionnaire" cookie and if the user hasn't already submitted a questionnaire
	 *
	 * @return bool
	 */
	public static function shouldShowQuestionnaire() : bool {
		return self::isShowQuestionnaireEnabled() && !self::getQuestionnaireUser()->finished;
	}

	/**
	 * Check if the user clicked the "Don't show questionnaire" button
	 *
	 * @return bool
	 */
	public static function isShowQuestionnaireEnabled() : bool {
		return !isset($_COOKIE['dont_show_questionnaire']) || !$_COOKIE['dont_show_questionnaire'];
	}

	/**
	 * Get current questionnaire user object
	 *
	 * @return User
	 */
	public static function getQuestionnaireUser() : User {
		$identification = self::getUserIdentification();
		/** @var User|null $user */
		$user = User::query()->where('[identif] = %s', $identification)->first();
		if (isset($user)) {
			return $user; // User found
		}
		// Create user
		$user = new User();
		$user->identif = $identification;
		try {
			if ($user->save()) {
				return $user;
			}
		} catch (ValidationException $e) {
		}
		throw new \RuntimeException('Failed to create a new questionnaire user');
	}

	/**
	 * Get unique user identification for questionnaire - set in cookie
	 *
	 * @post The cookie wil be set if it was not
	 *
	 * @return string
	 */
	public static function getUserIdentification() : string {
		if (isset($_COOKIE['questionnaire_identification'])) {
			return $_COOKIE['questionnaire_identification'];
		}

		// Generate new unique
		$identification = base64_encode(uniqid('questionnaire_', true));
		$_COOKIE['questionnaire_identification'] = $identification;
		setcookie('questionnaire_identification', $identification, time() + 3600 * 24 * 30, '/'); // Expire in 30 days
		return $identification;
	}

}