<?php


namespace app\controllers;


use app\models\CreateResponseForm;
use app\models\CreateReviewForm;
use app\models\CreateTaskForm;
use app\models\Category;
use app\models\Response;
use app\models\TaskFilterForm;
use app\services\TaskService;
use app\models\Task;
use Yii;
use yii\base\Exception;
use yii\base\UserException;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\widgets\ActiveForm;

class TasksController extends SecuredController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'view'],
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['create'],
                        'roles' => ['customer'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['cancel'],
                        'roles' => ['customerCanCancelTask'],
                        'roleParams' => fn($rule) => [
                            'task' => Task::findOne(Yii::$app->request->get('id'))
                        ]
                    ],
                    [
                        'allow' => true,
                        'actions' => ['refuse'],
                        'roles' => ['performerCanRefuseTask'],
                        'roleParams' => fn($rule) => [
                            'task' => Task::findOne(Yii::$app->request->get('id'))
                        ]
                    ],
                ],
            ],
        ];
    }

    /**
     * main tasks page with new tasks
     * @return string
     */
    public function actionIndex(): string
    {
        $this->view->title = 'Новые задания';

        $categoriesList = Category::getCategoryList();
        $filterForm = Yii::$container->get(TaskFilterForm::class);
        $filterForm->load(Yii::$app->request->get());
        $query = TaskService::filterTasks($filterForm);

        $tasksDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => Yii::$app->params['tasksListSize'],
            ],
            'sort' => [
                'defaultOrder' => [
                    'public_date' => SORT_DESC,
                ]
            ],
        ]);

        return $this->render('index', [
            'tasksDataProvider' => $tasksDataProvider,
            'filterForm' => $filterForm,
            'categoriesList' => $categoriesList,
        ]);
    }

    /**
     * to view $id task at personal page
     * @param int $id
     * @return string
     * @throws Exception
     */
    public function actionView(int $id): string
    {
        $task = Task::findOne($id);
        if (!$task) {
            throw new NotFoundHttpException("Задание с Id='$id' не найдено...");
        }

        $this->view->title = "$task->title";

        $taskStatusNameRu = Task::STATUSES_RU[$task->status];

        $responses = Response::find()->where(['task_id' => $id])->all();
        $files = $task->files;
        $availableUserActions = TaskService::getAvailableTaskActions(Yii::$app->user->identity, $task);
        $locationData = [];
        if (isset($task->city_id)) {
            $locationData = [
                'lat' => $task->lat,
                'long' => $task->long,
                'address' => $task->address,
                'city' => $task->city->name
            ];
        }

        return $this->render('view',
            [
                'task' => $task,
                'taskStatusNameRu' => $taskStatusNameRu,
                'responses' => $responses,
                'files' => $files,
                'reviewForm' => Yii::$container->get(CreateReviewForm::class),
                'responseForm' => Yii::$container->get(CreateResponseForm::class),
                'taskUserActions' => $availableUserActions,
                'locationData' => $locationData
            ]);
    }

    /**
     * Create new task
     * @return array|string|\yii\web\Response
     * @throws Exception
     */
    public function actionCreate(): \yii\web\Response|array|string
    {
        $this->view->title = "Создать задание";

        $user = Yii::$app->user->identity;
        $userLocationData = [
            'location' => 'Россия, ' . $user->city->name,
            'city' => $user->city->name,
            'address' => $user->city->name,
            'lat' => $user->city->lat,
            'long' => $user->city->long
        ];
        $categoriesList = Category::getCategoryList();
        $createTaskForm = new CreateTaskForm();

        //Ajax form validation
        if (Yii::$app->request->isAjax && $createTaskForm->load(Yii::$app->request->post())) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ActiveForm::validate($createTaskForm);
        }

        //validate and create new task
        if ($createTaskForm->load(Yii::$app->request->post()) && $createTaskForm->validate()) {
            $uploadedFiles = UploadedFile::getInstances($createTaskForm, 'files');
            $task = (new TaskService())->createTask($createTaskForm);

            if (!empty($uploadedFiles)) {
                foreach ($uploadedFiles as $uploadedFile) {
                    $fileService = Yii::$container->get('FileService', ['task', $task->id]);
                    $file = $fileService->upload($uploadedFile);
                    $task->link('files', $file);
                }
            }
            return $this->redirect(['tasks/view', 'id' => $task->id]);
        }


        return $this->render('create', [
            'createTaskForm' => $createTaskForm,
            'categoriesList' => $categoriesList,
            'userLocationData' => $userLocationData
        ]);
    }

    /**
     * cancel new task - for customer
     * @param int $id
     * @return \yii\web\Response
     * @throws Exception
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionCancel(int $id): \yii\web\Response
    {
        $task = Task::findOne($id);
        if (!$task) {
            throw new NotFoundHttpException("Задание с id=$id не найдено");
        }

        TaskService::cancelTask($task);

        Yii::$app->session->setFlash('success', 'Задача успешно отменена!');
        return $this->redirect(['tasks/view', 'id' => $id]);
    }

    /**
     * refuse "in_work" task - for performer
     * @param int $id
     * @return \yii\web\Response
     * @throws Exception
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function actionRefuse(int $id): \yii\web\Response
    {
        $task = Task::findOne($id);
        if (!$task) {
            throw new NotFoundHttpException("Не найдено задачи с Id=$id");
        }

        TaskService::refuseTask($task);

        return $this->redirect(['tasks/view', 'id' => $id]);
    }
}