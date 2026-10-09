<?php
function report_bar_chart(string $title,array $values,string $description,bool $currency=true): void {
    section_start($title,$description);
    $maximum=max(1,...array_values($values));
    echo '<div class="report-chart" role="img" aria-label="'.e($title).'">';
    foreach($values as $label=>$value) {
        $width=round(max(0,$value)/$maximum*100,2);
        echo '<div class="chart-row"><span>'.e($label).'</span><div class="chart-track"><div style="width:'.$width.'%"></div></div><strong>'.($currency?money($value):(int)$value).'</strong></div>';
    }
    if(!array_sum($values)) echo '<p class="empty">No data for this chart yet.</p>';
    echo '</div></section>';
}
function collection_charts(string $from,string $to,int $cash,int $gcash): void {
    $monthly=[];
    foreach(rows("SELECT DATE_FORMAT(payment_date,'%Y-%m') month,SUM(amount) total FROM payments WHERE voided_at IS NULL AND payment_date BETWEEN ? AND ? GROUP BY month ORDER BY month",[$from,$to]) as $r) $monthly[$r['month']]=(int)$r['total'];
    echo '<div class="report-chart-grid">';
    report_line_chart($monthly?:['No collections'=>0]);
    report_donut_chart('Payment methods',['GCash'=>$gcash,'Personal payment'=>$cash],'Confirmed collections within the selected reporting dates.',true);
    echo '</div>';
    $statuses=['Paid'=>0,'Partial'=>0,'Unpaid'=>0,'Overdue'=>0];
    foreach(aging_rows() as $b) { $label=ucfirst(status($b)); if(isset($statuses[$label])) $statuses[$label]++; }
    report_donut_chart('Current statement status',$statuses,'Live current statements, independent of the collection date filter.',false);
}
function report_line_chart(array $values): void {
    section_start('Monthly collections','Confirmed collections in the selected period. Hover over a point for the exact amount.');
    $max=max(1,...array_values($values)); $count=count($values); $points=[];$labels=array_keys($values);
    foreach(array_values($values) as $i=>$value) $points[]=[40+($count===1?240:$i*480/($count-1)),180-$value/$max*145];
    echo '<div class="report-chart line-chart"><svg viewBox="0 0 560 230" role="img" aria-label="Monthly collections trend"><path d="M40 25V180H530" fill="none" stroke="#dce5df"/><polyline fill="none" stroke="#20786a" stroke-width="3" points="'.implode(' ',array_map(fn($p)=>implode(',',$p),$points)).'"/>';
    foreach($points as $i=>[$x,$y]) { echo '<circle cx="'.$x.'" cy="'.$y.'" r="5" fill="#20786a"><title>'.e($labels[$i].': '.money(array_values($values)[$i])).'</title></circle>'; if($count<=8||$i===0||$i===$count-1) echo '<text x="'.$x.'" y="205" text-anchor="middle" fill="#60766f" font-size="12">'.e($labels[$i]).'</text>'; }
    echo '</svg><details><summary>View collection totals</summary>'; foreach($values as $label=>$amount) echo '<p>'.e($label).' <strong>'.money($amount).'</strong></p>'; echo '</details></div></section>';
}
function report_donut_chart(string $title,array $values,string $description,bool $currency): void {
    section_start($title,$description);$total=array_sum($values);$offset=0;$colors=['#20786a','#e0ad57','#5978ac','#c56b69'];
    echo '<div class="report-chart donut-chart"><svg viewBox="0 0 180 180" role="img" aria-label="'.e($title).' distribution"><circle cx="90" cy="90" r="65" fill="none" stroke="#edf1ee" stroke-width="22"/>';
    $index=0;foreach($values as $label=>$value) {$fraction=$total?$value/$total:0;$length=$fraction*408.407;echo '<circle cx="90" cy="90" r="65" fill="none" stroke="'.$colors[$index++%4].'" stroke-width="22" stroke-dasharray="'.$length.' '.(408.407-$length).'" stroke-dashoffset="'.(-$offset).'" transform="rotate(-90 90 90)"><title>'.e($label.': '.($currency?money($value):$value)).'</title></circle>';$offset+=$length;}
    echo '<text x="90" y="85" text-anchor="middle" fill="#63796f" font-size="12">'.($currency?'COLLECTED':'STATEMENTS').'</text><text x="90" y="106" text-anchor="middle" fill="#183a31" font-size="'.($currency?'13':'24').'" font-weight="700">'.($currency?money($total):$total).'</text></svg><div class="chart-legend">';
    $index=0;foreach($values as $label=>$value) echo '<div><i style="background:'.$colors[$index++%4].'"></i><span>'.e($label).'</span><strong>'.($currency?money($value):$value).'</strong></div>';echo '</div></div></section>';
}
