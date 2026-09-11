<?php
function expenseReportMonth($value): DateTimeImmutable {
    if (!is_string($value) || !preg_match('/^(?:[1-9][0-9]{3})-(?:0[1-9]|1[0-2])$/D', $value)) throw new InvalidArgumentException('Tháng không hợp lệ. Vui lòng chọn tháng theo định dạng YYYY-MM.');
    return new DateTimeImmutable($value.'-01', new DateTimeZone('Asia/Ho_Chi_Minh'));
}
// Sum DECIMAL(10,2) as integer hundredths, not floating point.
function expenseReportCents(string $amount): int {
    if (!preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/D', $amount, $parts)) throw new RuntimeException('Số tiền không hợp lệ.');
    $cents=(int)$parts[2]*100+(int)str_pad($parts[3]??'',2,'0');
    return $parts[1]==='-' ? -$cents : $cents;
}
function expenseReportMoney(int $cents): string {
    $absolute=abs($cents);
    return ($cents<0?'-':'').number_format(intdiv($absolute,100),0,',','.').($absolute%100 ? ','.str_pad((string)($absolute%100),2,'0',STR_PAD_LEFT) : '').'đ';
}
function expenseReportRead(mysqli $db, DateTimeImmutable $month): array {
    // These database columns are DATE, so an inclusive last day also handles 9999-12.
    $from=$month->format('Y-m-d'); $until=$month->format('Y-m-t');
    $days=[]; $pending=[]; $total=0; $pendingTotal=0; $itemCount=0;
    // Consistent reads even when a shift is closed/reopened during this request.
    $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try {
        $statement=$db->prepare('SELECT report_date, expenses FROM cashbook_history WHERE report_date >= ? AND report_date <= ? ORDER BY report_date');
        $statement->bind_param('ss',$from,$until); $statement->execute();
        foreach($statement->get_result() as $row) {
            $amount=expenseReportCents((string)$row['expenses']);
            $days[$row['report_date']]=['amount'=>$amount,'detail_total'=>0,'items'=>[]]; $total+=$amount;
        }
        $statement->close();
        $statement=$db->prepare('SELECT id, expense_date, amount, note FROM cash_expenses WHERE expense_date >= ? AND expense_date <= ? ORDER BY expense_date, id');
        $statement->bind_param('ss',$from,$until); $statement->execute();
        foreach($statement->get_result() as $row) {
            $row['cents']=expenseReportCents((string)$row['amount']);
            if(isset($days[$row['expense_date']])) {
                $days[$row['expense_date']]['items'][]=$row;
                $days[$row['expense_date']]['detail_total']+=$row['cents']; $itemCount++;
            } else { $pending[]=$row; $pendingTotal+=$row['cents']; }
        }
        $statement->close(); $db->commit();
    } catch(Throwable $error) { $db->rollback(); throw $error; }
    return ['days'=>$days,'total'=>$total,'item_count'=>$itemCount,'pending'=>$pending,'pending_total'=>$pendingTotal];
}
