<?

namespace App\Controllers;

use App\Models\Auth;
use App\Models\Search;
use App\Models\Generator;
use App\Models\Telegram;
use App\Models\Update;
use App\Models\User;
use Config\Services;

class Meters extends BaseController
{
    private $user;
    private $update;
    private $search;
    private $generator;
    private $auth;
    private $telegram;

    public function __construct()
    {
        $this->user = new User();
        $this->update = new Update();
        $this->search = new Search();
        $this->generator = new Generator();
        $this->auth = new Auth();
        $this->telegram = new Telegram();
    }

    public function plug()
    {
        return view('plug');
    }

    public function index()
    {
        helper(['form']);

        if (session('isLoggedIn') || $this->user->auth()) {
            return redirect()->to('meters');
        }

        return view('login');
    }

    public function login()
    {
        helper(['form', 'cookie']);

        $login = $this->request->getVar('log');

        if (!$this->validate(Services::validation()->getRuleGroup('login'))) {
            return view('login', [
                'validation' => $this->validator
            ]);
        } else {
            if (!$this->user->checkUser($login, $this->request->getVar('pw'))) {
                return view('login', [
                    'validation' => $this->validator
                ]);
            }
            session()->set('isLoggedIn', true);
            $this->user->addUserLog($login, ['login' => $login, 'message' => "Увійшов в систему"]);
        }

        return redirect()->to('meters');
    }

    public function logout()
    {
        $this->user->deleteCookies();
        session()->destroy();

        return redirect()->to('/');
    }

    public function password()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/');
        }

        if (!$this->validate(Services::validation()->getRuleGroup('password'))) {
            return json_encode(['validation' => $this->validator->listErrors()]);
        }

        if ($this->user->changePassword($this->request->getVar('confPw'))) {
            return false;
        }

        return json_encode(['validation' => $this->validator->listErrors()]);
    }

    public function meters()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/');
        }

        helper(['form']);

        return view('meters', array_merge([
            'department' => $this->search->getAllUnitDB(),
            'usDepartment' => $this->search->getDepartmentByUserId(session()->get('usId')),
            'allRights' => $this->search->getAllRights(),
            'pokazYear' => $this->search->getPokazYear(),
            'counterType' => $this->search->getCounterType(),
            'counterArea' => $this->search->getCounterArea(),
            'usAreaName' => $this->search->getUnitById(session()->get('usArea')),
            'companies' => $this->search->getCompanies(),
            'companiesAreas' => $this->search->getCompaniesAreas(),
            'generatorArea' => $this->generator->getGeneratorArea(),
            'canisterArea' => $this->generator->getCanisterArea(),
            'typeGenerator' => $this->generator->getTypeGenerator(),
            'canisterStatus' => $this->generator->getCanisterStatus()
        ], $this->user->getCounterDate()));
    }

    public function ajax()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/');
        }

        switch ($this->request->getVar('action')) {
            case 'getCounters':
                return $this->search->getCounters($this->request);
            case 'getCountersStat':
                return $this->search->getCountersStat($this->request);
            case 'getLastPokaz':
                return $this->search->getLastPokaz($this->request);
            case 'addPokaz':
                return $this->search->addPokaz($this->request);
            case 'addCounter':
                return $this->search->addCounter($this->request);
            case 'editPokaz':
                return $this->search->editPokaz($this->request);
            case 'getPokazByIdAndCounter':
                return $this->search->getPokazByIdAndCounter($this->request);
            case 'getLogConnection':
                return $this->search->getLogConnection();
            case 'usADD':
                return $this->search->usADD($this->request);
            case 'unADD':
                return $this->search->unADD($this->request);
            case 'getAllUnit':
                return $this->search->getAllUnit();
            case 'unitEDIT':
                return $this->search->unitEDIT($this->request);
            case 'getAllUser':
                return $this->search->getAllUser($this->request);
            case 'userEDIT':
                return $this->search->userEdit($this->request);
            case 'getReportCounter':
                return $this->search->getReportCounter($this->request);
            case 'modifGenerator':
                return $this->generator->modifGenerator($this->request);
            case 'getGenerators':
                return $this->generator->getGenerators($this->request);
            case 'getCanisters':
                return $this->generator->getCanisters($this->request);
            case 'addCanister':
                return $this->generator->addCanister($this->request);
            case 'canisterWritingOff':
                return $this->generator->canisterWritingOff($this->request);
            case 'addGeneratorPokaz':
                return $this->generator->addGeneratorPokaz($this->request);
            case 'confirmCanister':
                return $this->generator->confirmCanister($this->request);
            case 'getGeneratorsAndCanisters':
                return $this->generator->getGeneratorsAndCanisters($this->request);
            default:
                return 404;
        }
    }

    public function telegram()
    {
        $res = $this->auth->auth();
        if ($res->status != 200) {
            return $this->response->setStatusCode(401)->setJSON(["error" => $res->error]);
        } else {
            $res = $this->telegram->telegram($this->request->getJSON());
            if ($res['code'] == 200) {
                return $this->response->setStatusCode($res['code'])->setJSON($res['json']);
            } else {
                return $this->response->setStatusCode($res['code'])->setJSON(["error" => $res['error']]);
            }
        }
    }

    public function telegramUpdates()
    {
        $res = $this->auth->auth();
        if ($res->status != 200) {
            return $this->response->setStatusCode(401)->setJSON(["error" => $res->error]);
        } else {
            $res = $this->telegram->telegramUpdates($this->request->getJSON());
            return $this->response->setStatusCode($res['code'])->setJSON($res['json']);
        }
    }

    public function updateDB()
    {
        switch ($this->request->getVar('action')) {
            case 'addCompanies':
                return $this->update->addCompanies($this->request->getVar('companies'));
            case 'addCompaniesTradePoints':
                return $this->update->addCompaniesTradePoints($this->request->getVar('companiesTradePoints'));
            default:
                return 404;
        }
    }
}