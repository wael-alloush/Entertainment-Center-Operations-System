import React from "react";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
const RevnueChart = () => {
  const data = [
    { month: "jan", halfHour: 734, hour: 255, towHours: 691, openTime: 412 },
    { month: "feb", halfHour: 158, hour: 643, towHours: 251, openTime: 588 },
    { month: "mar", halfHour: 529, hour: 796, towHours: 413, openTime: 324 },
    { month: "apr", halfHour: 602, hour: 127, towHours: 759, openTime: 411 },
    { month: "may", halfHour: 444, hour: 550, towHours: 336, openTime: 698 },
    { month: "jun", halfHour: 736, hour: 275, towHours: 512, openTime: 589 },
    { month: "jul", halfHour: 281, hour: 697, towHours: 643, openTime: 233 },
    { month: "aug", halfHour: 518, hour: 421, towHours: 184, openTime: 621 },
    { month: "sep", halfHour: 366, hour: 724, towHours: 705, openTime: 489 },
    { month: "oct", halfHour: 681, hour: 392, towHours: 123, openTime: 758 },
    { month: "nov", halfHour: 228, hour: 547, towHours: 412, openTime: 603 },
    { month: "dec", halfHour: 715, hour: 313, towHours: 277, openTime: 504 },
  ];

  const SessionDurations = [
    {
      name: "نصف ساعة",
      nameen: "halfHour",
      color: "oklch(66.7% 0.295 322.15)",
      value: 40,
    },
    { name: "ساعة", nameen: "hour", color: "oklch(70.8% 0 0)", value: 20 },
    {
      name: "ساعتين",
      nameen: "towHours",
      color: "oklch(64.5% 0.246 16.439)",
      value: 15,
    },
    {
      name: "مفتوح",
      nameen: "openTime",
      color: "oklch(42.4% 0.199 265.638)",
      value: 25,
    },
  ];

  return (
    <div
      dir="ltr"
      className="bg-base-100 backdrop-blure-xl rounded-2xl border border-base-300 p-6"
    >
      <div dir="rtl" className="flex items-center justify-between mb-6">
        <div>
          <h3 className="text-xl font-bold text-base-content">
            احصائيات المبيعات
          </h3>
          <p className="text-sm text-base-content/50">مبيعات الجلسات لكل شهر</p>
        </div>
        <div className="flex items-center flex-wrap space-x-4">
          {SessionDurations.map((session) => (
            <div key={session.nameen} className="flex items-center space-x-2">
              <div
                className={`w-3 h-3    rounded-full`}
                style={{ backgroundColor: session.color }}
              ></div>
              <div className="text-sm text-base-content">
                <span>{session.name}</span>
              </div>
            </div>
          ))}
        </div>
      </div>
      <div className="h-80">
        {/* dir : rtl */}
        <ResponsiveContainer width="100%" height="100%">
          <BarChart
            data={data}
            margin={{ top: 20, right: 20, left: 0, bottom: 5 }}
            dataKey={"name"}
          >
            <CartesianGrid
              strokeDasharray="3 3"
              stroke="var(--base-content)"
              opacity={0.3}
            />
            <XAxis
              dataKey="month"
              stroke="#64748b"
              fontSize={12}
              tickLine={false}
              axisLine={false}
            />
            <YAxis
              stroke="#64748b"
              fontSize={12}
              tickLine={false}
              axisLine={false}
            />
            <Tooltip
              contentStyle={{
                backgroundColor: "var(--base-200)",
                border: "none",
                borderRadius: "12px",
                boxShadow: "0 10px 40px rgba(0 , 0 , 0,0.1)",
                color: "var(--base-content)",
              }}
              formatter={(Value) => [`${Value.toLocaleString()}`, ""]}
            />

            {[...SessionDurations].reverse().map((session) => (
              <Bar
                key={session.nameen}
                dataKey={session.nameen}
                fill={session.color}
                radius={[4, 4, 0, 0]}
                maxBarSize={40}
              />
            ))}
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
};

export default RevnueChart;
