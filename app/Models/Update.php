<?php

namespace App\Models;

use CodeIgniter\Model;
use Exception;

class Update extends Model
{
    protected $DBGroup = 'meters';

    public function changePW($usId, $pass)
    {
        $this->db->table('user')->update(['pass' => $pass], ['id' => $usId]);
    }

    public function addCompanies($companies)
    {
        try {
            $this->db->transStart();
            $companies_1s_code = $this->db->query("SELECT company_1s_code FROM companies")->getResultArray();
            $codes = [];
            foreach ($companies_1s_code as $code) {
                $codes[$code['company_1s_code']] = $code['company_1s_code'];
            }

            foreach ($companies as $company) {
                if (isset($codes[$company->company_1s_code])) {
                    $this->db->table('companies')->update($company, ['company_1s_code' => $company->company_1s_code]);
                } else $this->db->table('companies')->insert($company);
            }
            $this->db->transComplete();
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["error" => "Caught exception: " .  $e->getMessage()]);
        }
    }

    public function addCompaniesTradePoints($companiesTradePoints)
    {
        try {
            $this->db->transStart();
            $tradePoints = $this->db->query("SELECT id, trade_point_id FROM area WHERE trade_point_id != 0")->getResultArray();
            $code_tradePoints = [];
            foreach ($tradePoints as $tradePoint) {
                $code_tradePoints[$tradePoint['trade_point_id']] = $tradePoint['id'];
            }

            $companiesAreas = $this->db->query("SELECT company_1s_code, area_id FROM companiesAreas")->getResultArray();
            $code_companiesAreas = [];
            foreach ($companiesAreas as $companyArea) {
                $code_companiesAreas[$companyArea['area_id']] = $companyArea['company_1s_code'];
            }

            $problemTradePointId = [];
            foreach ($companiesTradePoints as $tradePoint) {
                $tradePointId = $tradePoint->trade_point_id;
                if (isset($code_tradePoints[$tradePointId])) {
                    $areaId = $code_tradePoints[$tradePointId];
                    if (isset($code_companiesAreas[$areaId])) {
                        $this->db->table('companiesAreas')->update(['company_1s_code' => $tradePoint->company_1s_code], ['area_id' => $areaId]);
                    } else $this->db->table('companiesAreas')->insert(['company_1s_code' => $tradePoint->company_1s_code, 'area_id' => $areaId]);
                } else array_push($problemTradePointId, $tradePointId);
            }
            $this->db->transComplete();
            if (count($problemTradePointId) != 0) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["error" => "Unknown trade points " .  json_encode($problemTradePointId)]);
            }
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["error" => "Caught exception: " .  $e->getMessage()]);
        }
    }
}
