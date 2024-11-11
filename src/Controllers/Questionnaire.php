<?php

namespace App\Controllers;

use App\Models\Questionnaire\Answer;
use App\Models\Questionnaire\User;
use App\Services\QuestionnaireHelper;
use Dibi\Exception;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Psr\Http\Message\ResponseInterface;

class Questionnaire extends Controller
{

	/**
	 * @return ResponseInterface
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 */
	public function resultsList() : ResponseInterface {
		$this->params['users'] = User::query()->where('id_questionnaire IS NOT NULL')->get();
		return $this->view('pages/questionnaire/index');
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 */
	public function resultsStats(Request $request) : ResponseInterface {
		// Long questionnaire
		$questionnaire = \App\Models\Questionnaire\Questionnaire::get(2);
		$this->params['questions'] = $questionnaire->getQuestions();
		$this->params['answers'] = [];
		$this->params['customs'] = [];

		/** @var string[] $filters */
		$filters = $request->get['filters'] ?? [];

		bdump($filters);

		// Get answers to an array that is easily processed
		/** @var Answer[] $userAnswers */
		$userAnswers = [];
		$answers = Answer::getAll();
		foreach ($answers as $answer) {
			if (!isset($this->params['customs'][$answer->question->id])) {
				$this->params['customs'][$answer->question->id] = [];
			}
			if (!isset($userAnswers[$answer->idUser])) {
				$userAnswers[$answer->idUser] = [];
			}
			$value = $answer->getValue();

			// Remove custom answer
			if (is_array($value) && isset($value['custom'])) {
				if (!empty($value['custom'])) {
					$this->params['customs'][$answer->question->id][] = $value['custom'];
				}
				unset($value['custom']);
			}

			// Skip unfilled answers
			if (empty($value)) {
				continue;
			}

			$userAnswers[$answer->idUser][$answer->question->id] = $value;
		}

		// Filter answers
		if (!empty($filters)) {
			$userAnswers = array_filter($userAnswers, static function(array $answers) use ($filters) {
				$match = true;
				foreach ($filters as $question => $value) {
					if (
						!isset($answers[$question]) ||
						(is_string($answers[$question]) && $answers[$question] !== $value) ||
						(is_array($answers[$question]) && !in_array($value, $answers[$question], true))
					) {
						$match = false;
						break;
					}
				}
				return $match;
			});
		}

		// Aggregate answers
		foreach ($userAnswers as $data) {
			foreach ($data as $questionId => $values) {
				if (!isset($this->params['answers'][$questionId])) {
					$this->params['answers'][$questionId] = [
						'values' => [],
						'total'  => 0,
					];
				}
				$this->params['answers'][$questionId]['total']++;
				if (is_string($values)) {
					$values = [$values];
				}
				foreach ($values as $value) {
					if (!isset($this->params['answers'][$questionId]['values'][$value])) {
						$this->params['answers'][$questionId]['values'][$value] = 0;
					}
					$this->params['answers'][$questionId]['values'][$value]++;
				}
			}
		}

		return $this->view('pages/questionnaire/stats');
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 */
	public function resultsUser(Request $request) : ResponseInterface {
		$id = (int) ($request->params['id'] ?? 0);
		if ($id < 1) {
			return $this->app->redirect('questionnaire-results');
		}
		$user = User::get($id);
		$this->params['user'] = $user;
		$this->params['questions'] = $user->questionnaire->getQuestions();
		return $this->view('pages/questionnaire/user');
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 */
	public function done(Request $request) : ResponseInterface {
		$user = QuestionnaireHelper::getQuestionnaireUser();
		foreach ($request->post['questionnaire'] ?? [] as $id => $values) {
			$test = DB::select(Answer::TABLE, Answer::getPrimaryKey())->where('id_question = %i AND id_user = %i', $id, $user->id)->fetchSingle();
			$data = [
				'id_question' => $id,
				'id_user'     => $user->id,
				'value'       => is_array($values) ? json_encode($values, JSON_THROW_ON_ERROR) : $values,
			];
			try {
				if (isset($test)) {
					DB::update(Answer::TABLE, $data, ['%n = %i', Answer::getPrimaryKey(), $test]);
				}
				else {
					DB::insert(Answer::TABLE, $data);
				}
			} catch (Exception $e) {
				return $this->respond(['error' => 'Failed saving answer to DB', 'exception' => $e->getMessage(), 'trace' => $e->getTrace(), 'sql' => $e->getSql()], 500);
			}
		}
		$user->finished = true;
		$user->save();
		$count = count($user->questionnaire->getQuestions());
		$this->params['counter'] = $count;
		$this->params['total'] = $count;
		return $this->respond([
										 'success' => true,
										 'total'   => $count,
										 'step'    => $count + 1,
										 'html'    => $this->latte->viewToString('questionnaire/questions/thank-you', $this->params),
									 ]);
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 */
	public function save(Request $request) : ResponseInterface {
		$user = QuestionnaireHelper::getQuestionnaireUser();
		foreach ($request->post['questionnaire'] ?? [] as $id => $values) {
			$test = DB::select(Answer::TABLE, Answer::getPrimaryKey())->where('id_question = %i AND id_user = %i', $id, $user->id)->fetchSingle();
			$data = [
				'id_question' => $id,
				'id_user'     => $user->id,
				'value'       => is_array($values) ? json_encode($values, JSON_THROW_ON_ERROR) : $values,
			];
			try {
				if (isset($test)) {
					DB::update(Answer::TABLE, $data, ['%n = %i', Answer::getPrimaryKey(), $test]);
				}
				else {
					DB::insert(Answer::TABLE, $data);
				}
			} catch (Exception $e) {
				return $this->respond(['error' => 'Failed saving answer to DB', 'exception' => $e->getMessage(), 'trace' => $e->getTrace(), 'sql' => $e->getSql()], 500);
			}
		}
		return $this->respond(['success' => true]);
	}

	/**
	 * Get question's HTML
	 *
	 * @param Request $request Allows passing "key" parameter to set which question to get
	 *
	 * @return ResponseInterface
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 */
	public function getQuestion(Request $request) : ResponseInterface {
		$key = (int) ($request->params['key'] ?? 0);
		$this->params['user'] = QuestionnaireHelper::getQuestionnaireUser();
		if (!isset($this->params['user']->questionnaire)) {
			return $this->respond(['error' => lang('User has no questionnaire set.', context: 'questionnaire.errors')], 400);
		}
		$questions = array_values($this->params['user']->questionnaire->getQuestions());
		bdump($key);
		bdump($questions);
		if (empty($questions)) {
			return $this->respond(['error' => lang('Questionnaire is empty.', context: 'questionnaire.errors')], 404);
		}
		$this->params['total'] = count($questions);
		// Get first unfilled question (or the last)
		if (!isset($questions[$key])) {
			foreach ($questions as $qKey => $question) {
				$answer = $this->params['user']->getAnswerForQuestion($question);
				if ($key !== -1 && (!isset($answer) || empty($answer->getValue()))) {
					break;
				}
				$key = $qKey;
			}
		}
		$this->params['counter'] = $key;
		$this->params['question'] = $questions[$key];
		bdump($questions);
		return $this->respond([
										 'step'  => $key + 1,
										 'total' => $this->params['total'],
										 'html'  => $this->latte->viewToString('questionnaire/questions/question', $this->params),
									 ]);
	}

	/**
	 * Sets selected questionnaire for user
	 *
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws ValidationException
	 */
	public function selectQuestionnaire(Request $request) : ResponseInterface {
		if (!isset($request->params['id'])) {
			return $this->respond(['error' => 'Missing parameter ID'], 400);
		}
		$id = (int) $request->params['id'];
		try {
			$questionnaire = \App\Models\Questionnaire\Questionnaire::get($id);
		} catch (ModelNotFoundException|DirectoryCreationException $e) {
			return $this->respond(['error' => 'Questionnaire not found', 'exception' => $e->getMessage()], 404);
		}
		$user = QuestionnaireHelper::getQuestionnaireUser();
		$user->questionnaire = $questionnaire;
		try {
			if ($user->save()) {
				return $this->respond(['success' => true]);
			}
		} catch (ValidationException $e) {
		}
		return $this->respond(['error' => 'Failed to save user'], 500);
	}

	public function showLater() : ResponseInterface {
		QuestionnaireHelper::showQuestionnaireLater();
		return $this->respond(['success' => true]);
	}

	public function dontShowAgain() : ResponseInterface {
		QuestionnaireHelper::dontShowQuestionnaire();
		return $this->respond(['success' => true]);
	}

}