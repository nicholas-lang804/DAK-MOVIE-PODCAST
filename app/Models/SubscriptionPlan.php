<?php
require_once __DIR__ . '/../../config/database.php';

class SubscriptionPlan {
    private $pdo;
    public function __construct() { $this->pdo = getDB(); $this->ensureDefaults(); }
    private function ensureDefaults() {
        if ((int)$this->pdo->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn() > 0) return;
        $stmt = $this->pdo->prepare("INSERT INTO subscription_plans (plan_key,name,duration_months,price,currency,discount_percent) VALUES (?,?,?,?,?,?)");
        foreach ([['monthly','Monthly Access',1,5,0],['yearly','Yearly Access',12,50,17],['two_year','Two-Year Access',24,90,25],['three_year','Three-Year Access',36,120,33]] as $plan) $stmt->execute([$plan[0],$plan[1],$plan[2],$plan[3],'USD',$plan[4]]);
    }
    public function getActive() { return $this->pdo->query("SELECT * FROM subscription_plans WHERE active=1 ORDER BY duration_months ASC")->fetchAll(); }
    public function findById($id) { $stmt=$this->pdo->prepare("SELECT * FROM subscription_plans WHERE id=? AND active=1"); $stmt->execute([(int)$id]); return $stmt->fetch(); }
    public function savePrice($key,$price,$currency,$discount) { $stmt=$this->pdo->prepare("UPDATE subscription_plans SET price=?,currency=?,discount_percent=? WHERE plan_key=?"); return $stmt->execute([(float)$price,strtoupper($currency),(float)$discount,$key]); }
}
