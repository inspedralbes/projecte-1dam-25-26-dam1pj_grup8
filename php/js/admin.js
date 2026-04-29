let trendChart;
let pagesChart;


async function cargarStats(){

let inicio=document.getElementById('fecha_inicio').value;
let fin=document.getElementById('fecha_fin').value;
let usuario=document.getElementById('usuario').value;
let pagina=document.getElementById('pagina').value;


let url=`admin_stats.php?
inicio=${inicio}
&fin=${fin}
&usuario=${usuario}
&pagina=${pagina}`;


let r=await fetch(url);
let data=await r.json();


document.getElementById('totalAccess').innerText=data.total;
document.getElementById('totalPages').innerText=data.pagesCount;
document.getElementById('activeUsers').innerText=data.usersCount;



let usersHTML='';

data.users.forEach(u=>{
usersHTML+=`
<tr>
<td>${u.username}</td>
<td>${u.total}</td>
</tr>
`;
});

document.getElementById('usersTable').innerHTML=usersHTML;


let pagesHTML='';

data.pages.forEach(p=>{
pagesHTML+=`
<tr>
<td>${p.page}</td>
<td>${p.total}</td>
</tr>
`;
});

document.getElementById('pagesTable').innerHTML=pagesHTML;



if(trendChart) trendChart.destroy();

trendChart=new Chart(
document.getElementById('trendChart'),
{
type:'line',
data:{
labels:data.trend.map(x=>x.dia),
datasets:[{
label:'Accessos',
data:data.trend.map(x=>x.total),
tension:0.4
}]
}
}
);



if(pagesChart) pagesChart.destroy();

pagesChart=new Chart(
document.getElementById('pagesChart'),
{
type:'bar',
data:{
labels:data.pages.map(x=>x.page),
datasets:[{
label:'Visites',
data:data.pages.map(x=>x.total)
}]
}
}
);

}


window.onload=cargarStats;