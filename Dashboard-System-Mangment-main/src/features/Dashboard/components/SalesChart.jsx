import React from "react";

import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from "recharts";

const SessionDurations = [
  {
    name: "نصف ساعة",
    nameen: "halfHour",
    color: "oklch(66.7% 0.295 322.15)",
    value: 1000,
  },
  { name: "ساعة", nameen: "hour", color: "oklch(70.8% 0 0)", value: 500 },
  {
    name: "ساعتين",
    nameen: "towhours",
    color: "oklch(64.5% 0.246 16.439)",
    value: 1500,
  },
  {
    name: "مفتوح",
    nameen: "opentime",
    color: "oklch(42.4% 0.199 265.638)",
    value: 2000,
  },
];
const SalesChart = () => {
  return (
    <div className="bg-base-100 backdrop-blur-xl rounded-2xl border border-base-300 p-6">
      <div className="mb-6">
        <h3 className="text-lg font-bold text-base-content">
          المبيعات حسب المنتج
        </h3>
        <p className="text-sm text-base-content/50">المنتجات</p>
      </div>
      <div className="h-48">
        <ResponsiveContainer height="100%" width="100%">
          <PieChart>
            <Pie
              data={SessionDurations}
              cx="50%"
              cy="50%"
              innerRadius={40}
              outerRadius={80}
              paddingAngle={5}
              dataKey="value"
            >
              {SessionDurations.map((entry, index) => (
                <Cell key={index} fill={entry.color} />
              ))}
            </Pie>
            <Tooltip
              contentStyle={{
                backgroundColor: "var(--base-200)",
                border: "none",
                borderRadius: "12px",
                boxShadow: "0 10px 40px rgba(0 , 0 , 0,0.1)",
                color: "var(--base-content)",
              }}
            />
          </PieChart>
        </ResponsiveContainer>
      </div>
      <div className="space-y-4">
        {SessionDurations.map((session) => (
          <div key={session.nameen} className="flex items-center space-x-2">
            <div className="flex itme-sce space-x-3">
              <div
                className={`w-3 h-3    rounded-full`}
                style={{ backgroundColor: session.color }}
              ></div>
            </div>
            <div className="text-sm text-base-content">
              <span>{session.name}</span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default SalesChart;
