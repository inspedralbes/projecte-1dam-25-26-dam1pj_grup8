let incidenciesStatusChart;
let incidenciesTypePriorityChart;
let accessTrendChart;


async function cargarStats(){

let inicio=document.getElementById('fecha_inicio').value;
let fin=document.getElementById('fecha_fin').value;
let usuario=document.getElementById('usuario').value;
let pagina=document.getElementById('pagina').value;

// Validar que si hay una fecha, la otra también debe existir
if ((inicio && !fin) || (!inicio && fin)) {
  alert('Les datas de inici i fi han de ser vàlides');
  return;
}

const params = new URLSearchParams({
inicio: (inicio || '').trim(),
fin: (fin || '').trim(),
usuario: (usuario || '').trim(),
pagina: (pagina || '').trim()
});

let url=`admin_stats.php?${params.toString()}`;

// Update "View JSON" link
const statsLink = document.getElementById('statsLink');
if (statsLink) {
  statsLink.href = url;
}

let data;
try {
  const r = await fetch(url, {
    headers: {
      'Accept': 'application/json'
    }
  });

  const contentType = (r.headers.get('content-type') || '').toLowerCase();
  if (!r.ok) {
    const text = await r.text();
    throw new Error(`HTTP ${r.status}: ${text.slice(0, 300)}`);
  }

  if (!contentType.includes('application/json')) {
    const text = await r.text();
    throw new Error(`Resposta no JSON: ${text.slice(0, 300)}`);
  }

  data = await r.json();
} catch (err) {
  console.error('No s\'han pogut carregar les estadístiques', err);
  alert('No s\'han pogut carregar les estadístiques. Revisa el link "View JSON" per veure l\'error.');
  return;
}

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


// Access trend chart
const trendData = Array.isArray(data.trend) ? data.trend : [];
const trendLabels = trendData.map(x => x.dia);
const trendTotals = trendData.map(x => parseInt(x.total || 0));

if(accessTrendChart) accessTrendChart.destroy();

accessTrendChart=new Chart(
document.getElementById('accessTrendChart'),
{
type:'line',
data:{
labels:trendLabels,
datasets:[{
label:'Accessos',
data:trendTotals,
borderColor:'#60a5fa',
backgroundColor:'rgba(96,165,250,0.2)',
fill:true,
tension:0.2
}]
},
options:{
responsive:true,
plugins:{
legend:{position:'bottom'}
},
scales:{
y:{beginAtZero:true}
}
}
}
);



const statusData = Array.isArray(data.incidencies?.status) ? data.incidencies.status : [];
const statusLabels = {
pendent_assignar: 'Pendent',
assignada: 'Assignada',
tancada: 'Tancada',
rebutjada: 'Rebutjada'
};
const statusColors = {
pendent_assignar: '#60a5fa',
assignada: '#001f3f',
tancada: '#22c55e',
rebutjada: '#ef4444'
};

if(incidenciesStatusChart) incidenciesStatusChart.destroy();

incidenciesStatusChart=new Chart(
document.getElementById('incidenciesStatusChart'),
{
type:'doughnut',
data:{
labels:statusData.map(x=>statusLabels[x.estat] || x.estat),
datasets:[{
label:'Incidències',
data:statusData.map(x=>x.total),
backgroundColor:statusData.map(x=>statusColors[x.estat] || '#cccccc'),
borderWidth:0
}]
},
options:{
responsive:true,
plugins:{
legend:{position:'bottom'}
}
}
}
);



if(incidenciesTypePriorityChart) incidenciesTypePriorityChart.destroy();

incidenciesTypePriorityChart=new Chart(
document.getElementById('incidenciesTypePriorityChart'),
{
type:'bar',
data:{
labels:Array.isArray(data.incidencies?.deptLabels) ? data.incidencies.deptLabels : [],
datasets:[{
label:'Alta',
data:Array.isArray(data.incidencies?.deptPriority?.Alta) ? data.incidencies.deptPriority.Alta : [],
backgroundColor:'#001f3f',
stack:'priority'
},{
label:'Mitja',
data:Array.isArray(data.incidencies?.deptPriority?.Mitja) ? data.incidencies.deptPriority.Mitja : [],
backgroundColor:'#60a5fa',
stack:'priority'
},{
label:'Baixa',
data:Array.isArray(data.incidencies?.deptPriority?.Baixa) ? data.incidencies.deptPriority.Baixa : [],
backgroundColor:'#0bedf5',
stack:'priority'
}]
},
options:{
responsive:true,
maintainAspectRatio:false,
scales:{
x:{stacked:true},
y:{stacked:true,beginAtZero:true}
},
plugins:{
legend:{position:'bottom'}
}
}
}
);

}


window.onload=cargarStats;

// When charts are created inside hidden tab panes, Chart.js may size them to 0.
// Resize on tab activation so the user sees them correctly.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(function (tabBtn) {
    tabBtn.addEventListener('shown.bs.tab', function (event) {
      const target = (event && event.target && event.target.getAttribute)
        ? event.target.getAttribute('data-bs-target')
        : null;

      if (target === '#logs') {
        if (accessTrendChart && typeof accessTrendChart.resize === 'function') {
          accessTrendChart.resize();
        }
      }

      if (target === '#incidencies') {
        if (incidenciesStatusChart && typeof incidenciesStatusChart.resize === 'function') {
          incidenciesStatusChart.resize();
        }
        if (incidenciesTypePriorityChart && typeof incidenciesTypePriorityChart.resize === 'function') {
          incidenciesTypePriorityChart.resize();
        }
      }
    });
  });
});