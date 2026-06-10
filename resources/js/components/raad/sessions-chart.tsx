import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartSessionDuration } from '@/types/domain';
import { BarChart3 } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface SessionsChartProps {
    data: Record<string, string | number>[];
    sessionDurations: ChartSessionDuration[];
}

export function SessionsChart({ data, sessionDurations }: SessionsChartProps) {
    return (
        <Card className="raad-card-surface">
            <CardHeader>
                <div dir="rtl" className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <span className="raad-icon-chip bg-violet-500/12 text-violet-600 dark:text-violet-400">
                                <BarChart3 className="size-4" strokeWidth={2.25} />
                            </span>
                            إحصائيات الفواتير الشهرية
                        </CardTitle>
                        <CardDescription>عدد الفواتير حسب مدة الجلسة لكل شهر</CardDescription>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {sessionDurations.map((session) => (
                            <div key={session.id} className="flex items-center gap-2 text-sm">
                                <span className="size-2.5 rounded-full" style={{ backgroundColor: session.color }} />
                                <span>{session.name}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div dir="ltr" className="h-80">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                            <XAxis dataKey="month" tickLine={false} axisLine={false} className="text-xs" />
                            <YAxis tickLine={false} axisLine={false} className="text-xs" />
                            <Tooltip
                                contentStyle={{
                                    backgroundColor: 'hsl(var(--card))',
                                    border: '1px solid hsl(var(--border))',
                                    borderRadius: '0.625rem',
                                }}
                            />
                            {[...sessionDurations].reverse().map((session) => (
                                <Bar key={session.key} dataKey={session.key} fill={session.color} radius={[4, 4, 0, 0]} maxBarSize={36} />
                            ))}
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
