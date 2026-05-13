import React, { useState, useEffect } from 'react';
import { api } from '../../services/api';
import { Cpu, Server, Database, Globe, RefreshCcw, Wifi, AlertTriangle } from 'lucide-react';
import LoadingSpinner from '../../components/ui/LoadingSpinner';

const Hardware: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [components, setComponents] = useState<any[]>([]);

  const fetchData = async () => {
    try {
      const data = await api.get<{ components: any[] }>('/admin/hardware');
      setComponents(data.components);
    } catch (err) {
      console.error('Failed to fetch hardware data', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, 30000);
    return () => clearInterval(interval);
  }, []);

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-8 animate-fade-in">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-black italic tracking-tighter uppercase mb-1">Status Infrastruktur</h1>
          <p className="text-chess-muted text-sm font-bold uppercase tracking-widest">Pemantauan Real-time Hardware Server CHESSID</p>
        </div>
        <button 
          onClick={() => { setLoading(true); fetchData(); }}
          className="flex items-center gap-2 px-4 py-2 bg-white hover:bg-slate-50 rounded-xl text-xs font-bold transition-all border border-slate-200 shadow-sm"
        >
          <RefreshCcw className="w-4 h-4" /> REFRESH
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatusCard title="Server Response" value="12ms" status="Online" icon={Server} color="text-emerald-500" />
        <StatusCard title="Database Latency" value="3ms" status="Optimal" icon={Database} color="text-emerald-500" />
        <StatusCard title="Global Traffic" value="4.2k req/s" status="Normal" icon={Globe} color="text-amber-500" />
        <StatusCard title="Network Uplink" value="1.2 Gbps" status="Online" icon={Wifi} color="text-emerald-500" />
      </div>

      <div className="bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
        <div className="p-6 border-b border-slate-50 bg-white flex items-center justify-between">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-widest flex items-center gap-2">
                <Cpu className="w-5 h-5 text-chess-indigo" /> Rincian Komponen Server
            </h3>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead>
              <tr className="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-50 bg-slate-50/50">
                <th className="px-8 py-4">Komponen</th>
                <th className="px-8 py-4">Status</th>
                <th className="px-8 py-4">IP Address</th>
                <th className="px-8 py-4">Beban CPU</th>
                <th className="px-8 py-4">Memori</th>
                <th className="px-8 py-4">Uptime</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {components.map((comp) => (
                <tr key={comp.id} className="hover:bg-slate-50/50 transition-colors group">
                  <td className="px-8 py-5">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center font-bold text-slate-400">
                            {comp.type === 'CPU' ? <Cpu className="w-5 h-5" /> : <Server className="w-5 h-5" />}
                        </div>
                        <div>
                            <p className="font-bold text-slate-800">{comp.name}</p>
                            <p className="text-[10px] uppercase font-bold text-slate-400">{comp.type}</p>
                        </div>
                    </div>
                  </td>
                  <td className="px-8 py-5">
                    <span className={`px-2 py-1 rounded-lg text-[10px] font-bold uppercase ${comp.status === 'Online' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}`}>
                        {comp.status}
                    </span>
                  </td>
                  <td className="px-8 py-5 font-mono text-xs text-slate-500">{comp.ip}</td>
                  <td className="px-8 py-5">
                    <div className="flex items-center gap-3">
                        <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden min-w-[80px]">
                            <div 
                                className={`h-full rounded-full transition-all duration-1000 ${comp.cpu > 80 ? 'bg-rose-500' : 'bg-emerald-500'}`} 
                                style={{ width: `${comp.cpu}%` }} 
                            />
                        </div>
                        <span className={`text-xs font-bold ${comp.cpu > 80 ? 'text-rose-500' : 'text-slate-400'}`}>{comp.cpu}%</span>
                    </div>
                  </td>
                  <td className="px-8 py-5">
                    <div className="flex items-center gap-3">
                        <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden min-w-[80px]">
                            <div 
                                className={`h-full rounded-full transition-all duration-1000 ${comp.ram > 80 ? 'bg-rose-500' : 'bg-emerald-500'}`} 
                                style={{ width: `${comp.ram}%` }} 
                            />
                        </div>
                        <span className={`text-xs font-bold ${comp.ram > 80 ? 'text-rose-500' : 'text-slate-400'}`}>{comp.ram}%</span>
                    </div>
                  </td>
                  <td className="px-8 py-5 text-sm font-medium text-slate-500">{comp.uptime}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

const StatusCard: React.FC<{ title: string, value: string, status: string, icon: any, color: string }> = ({ title, value, status, icon: Icon, color }) => (
  <div className="bg-white border border-slate-100 p-6 rounded-2xl shadow-sm hover:border-chess-indigo transition-all group">
    <div className="flex items-center justify-between mb-4">
        <div className={`p-3 rounded-xl bg-slate-50 group-hover:scale-110 transition-transform ${color}`}>
            <Icon className="w-5 h-5 font-bold" />
        </div>
        <span className={`text-[10px] font-bold uppercase tracking-widest ${color}`}>{status}</span>
    </div>
    <h3 className="text-3xl font-bold text-slate-800 mb-1">{value}</h3>
    <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">{title}</p>
  </div>
);

export default Hardware;
