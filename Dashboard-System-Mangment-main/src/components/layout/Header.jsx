import React from "react";
import { MenuIcon, Plus } from "lucide-react";
import { Search } from "lucide-react";
import { Filter } from "lucide-react";
import { Sun } from "lucide-react";
import { Moon } from "lucide-react";
import { Bell } from "lucide-react";
import { Settings } from "lucide-react";
import { ChevronDown } from "lucide-react";
const Header = ({ setmode, mode, setsidebarcollapsed }) => {
  const handlemode = () => {
    if (mode === "dark") {
      setmode("");
    } else setmode("dark");
  };

  return (
    <div className="bg-base-100 backdrop-blur-lg border-b border-base-300 px-6 py-4 ">
      <div className="flex items-center justify-between">
        {/* right section */}
        <div className="flex items-center space-x-4">
          <button
            onClick={setsidebarcollapsed}
            className="p-2 rounded-lg text-secondary hover:bg-base-200 transition-colors"
          >
            <MenuIcon className="w-5 h-5" />
          </button>
          <div className="hidden md:block">
            <h1 className="text-2xl font-black text-base-content">
              مركز أرض البراعم للترفيه
            </h1>
            <p className="text-sm text-base-content/70">نظام ادارة الجلسات</p>
          </div>
        </div>
        {/* center */}

        {/* left section */}
        <div className="flex items-center space-x-3">
          {/* quic action */}

          {/* toggle */}
          <button
            onClick={handlemode}
            className="p-2.5 rounded-xl  text-base-content hover:bg-base-200 transition-colors"
          >
            {mode === "dark" ? (
              <Sun className="w-5 h-5" />
            ) : (
              <Moon className="w-5 h-5" />
            )}
          </button>
          {/* notification */}
          <button
            className="relative p-2.5 rounded-xl text-base-content hover:bg-base-200 
          transition-colors"
          >
            <Bell className="w-5 h-5" />
            <span
              className="absolute -top-1 w-5 h-5 bg-info text-primary-content text-xs rounded-full
             flex-none items-center justify-center right-0 pt-0.25"
            >
              3
            </span>
          </button>
          {/* settings */}
          <button
            className="p-2.5 rounded-xl text-base-content hover:bg-base-200 
          transition-colors"
          >
            <Settings className="w-6 h-6" />
          </button>
          {/* user profile */}
          <div className="flex items-center space-x-3 pr-3 border-r border-base-200">
            <img
              src="https://plus.unsplash.com/premium_photo-1689568126014-06fea9d5d341?ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&q=80&w=1170"
              alt="user"
              className="w-8 h-8 rounded-full ring-2 ring-primary object-cover "
            />
            <div className="hidden md:block ">
              <p className="align-middel text-sm font-medium text-base-content">
                وائل العلوش
              </p>
              <p className="align-middel text-xs text-base-content/70">
                مدير النظام
              </p>
            </div>
            <ChevronDown className="w-4 h-4 text-base-content/70" />
          </div>
        </div>
      </div>
    </div>
  );
};

export default Header;
