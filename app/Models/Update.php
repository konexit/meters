<?php

namespace App\Models;

use CodeIgniter\Model;

class Update extends Model
{
    protected $DBGroup = 'meters';

    public function changePW($usId, $pass)
    {
        $this->db->table('user')->update(['pass' => $pass], ['id' => $usId]);
    }

    public function addCompanies($companies)
    {
        $this->db->table('companies')->insertBatch($companies);
    }

    public function addCompaniesTradePoints($companiesTradePoints)
    {
        $this->db->table('companiesTradePoints')->insertBatch($companiesTradePoints);
    }
}
