const peso=value=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(value/100);
document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(button.dataset.copy);button.textContent='Copied ✓';}catch{button.textContent='Number: '+button.dataset.copy;}}));
const paymentLimits=document.getElementById('payment-limits');
document.querySelectorAll('form').forEach(form=>{if(form.querySelector('[name="action"]')?.value!=='review_submission')return;const decision=form.querySelector('[name="decision"]'),note=form.querySelector('[name="review_note"]'),verified=form.querySelector('[name="verified"]');const update=()=>{const approve=decision.value==='approved';verified.required=approve;verified.closest('label').hidden=!approve;note.required=!approve;};decision.addEventListener('change',update);update();});
if(paymentLimits){const form=paymentLimits.closest('form'),limits=JSON.parse(paymentLimits.value),amount=form.querySelector('[name="amount"]');const hint=document.createElement('p');hint.className='submission-hint';form.querySelector('.form-actions').before(hint);const update=()=>{const available=limits[form.querySelector('[name="billing_id"]').value]||0;amount.max=(available/100).toFixed(2);amount.min='0.01';hint.textContent='You may submit up to '+peso(available)+'. Pending submissions do not reduce your bill until verified.';};form.querySelector('[name="billing_id"]').addEventListener('change',()=>{amount.value=((limits[form.querySelector('[name="billing_id"]').value]||0)/100).toFixed(2);update();});update();}
document.querySelectorAll('.table-wrap table:not(.batch-table)').forEach(table=>{
 const entries=Array.from(table.querySelectorAll('tbody tr')).filter(row=>!row.querySelector('.empty'));
 if(!entries.length)return;
 let page=0,term='',filter='';const pageSize=10;
 const panel=table.closest('.panel'),search=table.id?document.querySelector('[data-search="'+table.id+'"]'):null;
 const pager=document.createElement('div');pager.className='table-pager no-print';
 const label=document.createElement('span');label.className='pager-label';
 const previous=document.createElement('button');previous.type='button';previous.textContent='← Previous';
 const next=document.createElement('button');next.type='button';next.textContent='Next →';
 const pageLabel=document.createElement('span');pager.append(label,previous,pageLabel,next);table.parentNode.after(pager);
 const update=()=>{const matching=entries.filter(row=>row.textContent.toLowerCase().includes(term)&&(!filter||row.dataset.status===filter));page=Math.max(0,Math.min(page,Math.ceil(matching.length/pageSize)-1));entries.forEach(row=>row.hidden=true);matching.slice(page*pageSize,(page+1)*pageSize).forEach(row=>row.hidden=false);label.textContent=matching.length?(page*pageSize+1)+'–'+Math.min((page+1)*pageSize,matching.length)+' of '+matching.length+' records':'No matching records';pageLabel.textContent=(page+1)+' / '+Math.max(1,Math.ceil(matching.length/pageSize));previous.disabled=page===0;next.disabled=(page+1)*pageSize>=matching.length;};
 if(search){const toolbar=document.createElement('div');toolbar.className='table-toolbar';search.before(toolbar);toolbar.append(search);search.addEventListener('input',()=>{term=search.value.toLowerCase();page=0;update();});if(table.id==='bill-table'){const select=document.createElement('select');select.setAttribute('aria-label','Filter statement status');['All statuses','Unpaid','Partial','Paid','Overdue','Carried'].forEach((text,i)=>{const option=document.createElement('option');option.value=i?text.toLowerCase():'';option.textContent=text;select.append(option);});toolbar.append(select);select.addEventListener('change',()=>{filter=select.value;page=0;update();});}}
 previous.addEventListener('click',()=>{page--;update();});next.addEventListener('click',()=>{page++;update();});update();
});
const menu=document.getElementById('menu-toggle');
if(menu){const close=()=>{document.body.classList.remove('sidebar-open');menu.setAttribute('aria-expanded','false');};menu.addEventListener('click',()=>{const open=document.body.classList.toggle('sidebar-open');menu.setAttribute('aria-expanded',String(open));});document.getElementById('sidebar-scrim').addEventListener('click',close);document.addEventListener('keydown',event=>{if(event.key==='Escape')close();});}
document.querySelectorAll('[data-confirm]').forEach(button=>button.addEventListener('click',event=>{if(!window.confirm(button.dataset.confirm))event.preventDefault();}));
document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
const rentData=document.getElementById('tenant-rents');
if(rentData){const form=rentData.closest('form'),rents=JSON.parse(rentData.value);form.querySelector('[name="tenant_id"]').addEventListener('change',event=>{form.querySelector('[name="rent"]').value=rents[event.target.value]??0;});}
const preview=document.getElementById('statement-preview');
if(preview){const form=preview.closest('form'),balances=JSON.parse(document.getElementById('tenant-balances').value),defaults=JSON.parse(document.getElementById('billing-defaults').value);const amount=name=>Math.round((Number(form.elements.namedItem(name)?.value)||0)*100);const update=()=>{const charges=amount('rent')+amount('electricity')+amount('water'),previous=balances[form.elements.namedItem('tenant_id').value]||0,discount=amount('discount'),adjustment=amount('adjustment');document.getElementById('statement-total').textContent=peso(charges+previous-discount+adjustment);document.getElementById('preview-charges').textContent='Current charges '+peso(charges);document.getElementById('preview-previous').textContent='Previous balance '+peso(previous);document.getElementById('preview-discount').textContent='Discount '+peso(discount)+' · Adjustment '+peso(adjustment);};const dates=()=>{const period=form.elements.namedItem('period').value;if(!/^\d{4}-\d{2}$/.test(period))return;const [year,month]=period.split('-').map(Number),last=new Date(year,month,0).getDate();form.elements.namedItem('cutoff').value=period+'-'+String(Math.min(defaults.cutoff,last)).padStart(2,'0');form.elements.namedItem('due_date').value=period+'-'+String(Math.min(defaults.due,last)).padStart(2,'0');};form.addEventListener('input',update);form.addEventListener('change',update);form.elements.namedItem('period').addEventListener('change',dates);dates();update();}
const batch=document.querySelector('form:has([data-batch-row])');
const latestData=document.getElementById('tenant-latest-periods');
if(latestData){const form=latestData.closest('form'),latest=JSON.parse(latestData.value),initialPeriod=form.elements.namedItem('period').value;const suggest=()=>{const last=latest[form.elements.namedItem('tenant_id').value];let period=initialPeriod;if(last&&last>=period){const [y,m]=last.split('-').map(Number);period=(m===12?y+1:y)+'-'+String(m===12?1:m+1).padStart(2,'0');}form.elements.namedItem('period').value=period;form.elements.namedItem('period').dispatchEvent(new Event('change',{bubbles:true}));};form.elements.namedItem('tenant_id').addEventListener('change',suggest);suggest();}
if(batch){const update=()=>{let total=0;batch.querySelectorAll('[data-batch-row]').forEach(row=>{let sum=Number(row.dataset.rent)+Number(row.dataset.previous);row.querySelectorAll('input[type="number"]').forEach(input=>sum+=Math.round((Number(input.value)||0)*100));row.querySelector('[data-batch-total]').textContent=peso(sum);if(row.querySelector('input[type="checkbox"]').checked)total+=sum;});document.getElementById('batch-grand-total').textContent=peso(total);};const all=document.getElementById('select-all');all.addEventListener('change',()=>{batch.querySelectorAll('[name="selected[]"]').forEach(box=>box.checked=all.checked);update();});batch.addEventListener('input',update);batch.addEventListener('change',update);update();}
document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('button');if(button){button.disabled=true;setTimeout(()=>button.disabled=false,5000);}}));
document.querySelectorAll('form').forEach(form=>{
 const password=form.querySelector('[name="temporary_password"]'),confirmation=form.querySelector('[name="confirm_password"]');
 if(password&&confirmation){const validate=()=>confirmation.setCustomValidity(confirmation.value&&password.value!==confirmation.value?'The passwords do not match.':'');password.addEventListener('input',validate);confirmation.addEventListener('input',validate);}
});
const start=document.getElementById('start-camera');
if(start){
 let stream=null,model=null,running=false;
 const video=document.getElementById('camera'),canvas=document.getElementById('camera-overlay'),status=document.getElementById('camera-status');
 const stop=()=>{running=false;stream?.getTracks().forEach(t=>t.stop());stream=null;video.srcObject=null;canvas.getContext('2d').clearRect(0,0,canvas.width,canvas.height);document.getElementById('camera-placeholder').style.display='flex';start.disabled=false;status.textContent='Camera is off.';document.getElementById('live-count').textContent='—';document.querySelector('[name="source"]').value='manual';};
 const loadScript=url=>new Promise((resolve,reject)=>{const s=document.createElement('script');s.src=url;s.onload=resolve;s.onerror=()=>reject(new Error('Detection library could not be loaded. Check your internet connection.'));document.head.appendChild(s);});
 document.getElementById('stop-camera').addEventListener('click',stop);window.addEventListener('pagehide',stop);
 start.addEventListener('click',async()=>{
  start.disabled=true;running=true;
  try{
   if(!navigator.mediaDevices?.getUserMedia)throw new Error('Use http://localhost or HTTPS to access your camera.');
   status.textContent='Requesting camera permission…';
   stream=await navigator.mediaDevices.getUserMedia({video:{width:640,height:480},audio:false});
   if(!running){stream.getTracks().forEach(t=>t.stop());return;}
   video.srcObject=stream;await video.play();document.getElementById('camera-placeholder').style.display='none';status.textContent='Loading person detection model…';
   if(!window.tf)await loadScript('https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js');
   if(!window.cocoSsd)await loadScript('https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3/dist/coco-ssd.min.js');
   model=model||await cocoSsd.load({base:'lite_mobilenet_v2'});
   if(!running)return;
   status.textContent='Counting people. Verify the estimate before saving.';
   const detect=async()=>{
    if(!running)return;
    try{
     const found=(await model.detect(video,100,.55)).filter(p=>p.class==='person'&&p.score>=.55);
     if(!running)return;
     canvas.width=video.videoWidth;canvas.height=video.videoHeight;const ctx=canvas.getContext('2d');ctx.clearRect(0,0,canvas.width,canvas.height);
     ctx.strokeStyle='#95e5b7';ctx.lineWidth=3;ctx.font='16px Arial';ctx.fillStyle='#95e5b7';
     found.forEach(p=>{ctx.strokeRect(...p.bbox);ctx.fillText('Person '+Math.round(p.score*100)+'%',p.bbox[0]+4,Math.max(20,p.bbox[1]-5));});
     document.getElementById('live-count').textContent=found.length;document.getElementById('detected-count').value=found.length;document.querySelector('[name="source"]').value='camera';
     setTimeout(detect,750);
    }catch(error){stop();status.textContent='Detection stopped: '+error.message;}
   };detect();
  }catch(error){stop();status.textContent=error.name==='NotAllowedError'?'Camera permission was denied. You can record a manual verification.':error.message;}
 });
}

// Open the office form addressed by a dashboard or directory action.
function revealLinkedPanel(){const id=window.location.hash.slice(1);if(!id)return;const panel=document.getElementById(id);if(panel?.tagName==='DETAILS')panel.open=true;}
window.addEventListener('hashchange',revealLinkedPanel);revealLinkedPanel();
