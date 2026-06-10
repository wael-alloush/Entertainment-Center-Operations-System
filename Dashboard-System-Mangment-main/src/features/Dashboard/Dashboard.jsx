import React from "react";
import StatsGrid from "./components/StatsGrid.jsx";
import ChartSection from "./components/ChartSection.jsx";

const Dashboard = () => {
  return (
    <div className="space-y-6">
      {/* stats grid */}
      <StatsGrid />
      {/* chart */}
      <ChartSection />
    </div>
  );
};

export default Dashboard;
