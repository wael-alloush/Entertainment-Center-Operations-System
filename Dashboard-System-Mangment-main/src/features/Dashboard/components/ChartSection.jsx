import React from "react";
import RevnueChart from "./RevnueChart";
import SalesChart from "./SalesChart";
const ChartSection = () => {
  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div className="lg:col-span-2">
        <RevnueChart />
      </div>
      <div className="space-y-6">
        <SalesChart />
      </div>
    </div>
  );
};

export default ChartSection;
