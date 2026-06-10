import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SessionDistributionItem } from '@/types/domain';
import { PieChart as PieChartIcon } from 'lucide-react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

interface SessionDistributionChartProps {
    data: SessionDistributionItem[];
}

export function SessionDistributionChart({ data }: SessionDistributionChartProps) {
    return (
        <Card className="raad-card-surface">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <span className="raad-icon-chip bg-amber-500/12 text-amber-600 dark:text-amber-400">
                        <PieChartIcon className="size-4" strokeWidth={2.25} />
                    </span>
                    توزيع أنواع الجلسات
                </CardTitle>
                <CardDescription>نسبة الفواتير حسب مدة الجلسة</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="h-48">
                    <ResponsiveContainer height="100%" width="100%">
                        <PieChart>
                            <Pie data={data} cx="50%" cy="50%" innerRadius={45} outerRadius={75} paddingAngle={4} dataKey="value">
                                {data.map((entry) => (
                                    <Cell key={entry.id} fill={entry.color} />
                                ))}
                            </Pie>
                            <Tooltip />
                        </PieChart>
                    </ResponsiveContainer>
                </div>
                <div className="space-y-2">
                    {data.map((session) => (
                        <div key={session.id} className="flex items-center justify-between text-sm">
                            <div className="flex items-center gap-2">
                                <span className="size-2.5 rounded-full" style={{ backgroundColor: session.color }} />
                                <span>{session.name}</span>
                            </div>
                            <span className="text-muted-foreground font-medium">{session.value}</span>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
