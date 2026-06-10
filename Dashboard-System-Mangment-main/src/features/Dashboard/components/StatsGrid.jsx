import {
  ArrowLeft,
  BoxIcon,
  Check,
  DollarSign,
  Icon,
  Menu,
  PrinterIcon,
  ShoppingCart,
} from "lucide-react";
import React, { Activity } from "react";

const stats = [
  {
    title: "الاجمالي",
    value: "2,847",
    change: "+15.3%",
    trend: "up",
    Icon: DollarSign,
    progress: 75,
  },

  {
    title: "المستخدمين النشطين",
    value: "1,234",
    change: "-2.1%",
    trend: "down",
    Icon: Check,
    progress: 20,
  },
  {
    title: "المنتجات",
    value: "3,456",
    change: "+7.9%",
    trend: "up",
    Icon: BoxIcon,
    progress: 90,
  },
  {
    title: "الطلبات",
    value: "1,234",
    change: "-2.1%",
    trend: "down",
    Icon: ShoppingCart,
    progress: 40,
  },
];
const StatsGrid = () => {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
      {stats.map((stat, index) => (
        <div
          key={index}
          className="bg-base-100 text-base-content backdrop-blur-xl rounded-2xl p-6
            border border-base-300 hover:shadow-xl hover:shadow-base-300/50 transition-all duration-300
            group"
        >
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <p className="text-sm font-medium mb-2">{stat.title}</p>
              <p className="text-3xl font-bold text-base-content">
                {stat.value}
              </p>
              <div className="flex items-center space-x-2">
                {stat.trend === "up" ? (
                  <ArrowLeft className="w-4 h-4 rotate-45 text-emerald-500" />
                ) : (
                  <ArrowLeft className="w-4 h-4 -rotate-45 text-red-500" />
                )}

                <span
                  className={`text-sm font-semibold ${
                    stat.trend === "up" ? "text-emerald-500" : "text-red-500"
                  }`}
                >
                  {stat.change}
                </span>
                <span className="text-sm text-base-content/60">
                  من الشهر الماضي
                </span>
              </div>
            </div>
            <div
              className={`p-3 rounded-lg group-hover:scale-110 transition-all duration-200 bg-primary/10`}
            >
              {<stat.Icon className="w-6 h-6 text-primary" />}
            </div>
          </div>
          {/* progressBar */}
          <div className="mt-4 bg-base-300 rounded-full overflow-hidden">
            <div
              className={`w-full h-2 bg-gradient-to-r from-primary to-accent trandition-all duration-100`}
              style={{ width: stat.progress + "%" }}
            ></div>
          </div>
        </div>
      ))}
    </div>
  );
};

export default StatsGrid;
