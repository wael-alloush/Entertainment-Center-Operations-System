import React from "react";
import { useState } from "react";
import { Eye, Hourglass, RefreshCw, Filter } from "lucide-react";

// My UI components //
import Table from "../../../components/ui/table/Table";
import TrHead from "../../../components/ui/table/TrHead";
import TrBody from "../../../components/ui/table/TrBody";
import Th from "../../../components/ui/table/Th";
import Td from "../../../components/ui/table/Td";
import Input from "../../../components/ui/Input";
import Lable from "../../../components/ui/Lable";
import Select from "../../../components/ui/select";
import Modal from "../../../components/ui/Modal";
import Badge from "../../../components/ui/Badge";
import SessionCard from "./SessionCard";
import FloatButton from "./FloatButton";
import FilterSection from "./FilterSection";
import HeaderOfTable from "./HeaderOfTable";
import MyButton from "../../../components/ui/MyButton";

//== My UI components ==//

const bills = [
  {
    id: 1,
    billNumber: "123456",
    childrenNumber: 3,
    phoneNumber: "0599123456",
    sessionTimeid: 1,
    extends: [
      {
        id: 1,
        extendsessionTimeId: 2,
      },
    ],
    enterTime: "12:00",
    exitTime: "8:21",
  },
  {
    id: 2,
    billNumber: "123456",
    childrenNumber: 3,
    phoneNumber: "0599123456",
    sessionTimeid: 1,
    extends: [
      {
        id: 1,
        extendsessionTimeId: 4,
      },
    ],
    enterTime: "12:00",
    exitTime: "02:30",
  },
  {
    id: 3,
    billNumber: "123456",
    childrenNumber: 3,
    phoneNumber: "0599123456",
    sessionTimeid: 2,
    extends: [
      {
        id: 1,
        extendsessionTimeId: 3,
      },
    ],
    enterTime: "12:00",
    exitTime: "05:30",
  },
  {
    id: 4,
    billNumber: "123456",
    childrenNumber: 3,
    phoneNumber: "0599123456",
    sessionTimeid: 1,
    extends: [
      {
        id: 1,
        extendsessionTimeId: 2,
      },
    ],
    enterTime: "12:00",
    exitTime: "8:21",
  },
];

const sessionTime = [
  {
    id: 1,
    name: "نصف ساعة",
    sessionTime: 33,
    color: "oklch(66.7% 0.295 322.15)",
  },
  {
    id: 2,
    name: "ساعة",
    sessionTime: 65,
    color: "var(--primary)",
  },
  {
    id: 3,
    name: "ساعتين",
    sessionTime: 125,
    color: "oklch(64.5% 0.246 16.439)",
    // color: "oklch(64.5% 0.246 16.439)",
  },
  {
    id: 4,
    name: "مفتوح",
    sessionTime: null,
    color: "oklch(42.4% 0.199 265.638)",
  },
];

function diffInArabic(time1, time2) {
  // تقسيم الساعات والدقائق وتحويلها إلى أرقام
  const [h1, m1] = time1.split(":").map(Number);
  const [h2, m2] = time2.split(":").map(Number);

  // التحقق من أن الأوقات من نفس اليوم (0 <= الساعة < 24)
  if (
    h1 < 0 ||
    h1 >= 24 ||
    h2 < 0 ||
    h2 >= 24 ||
    m1 < 0 ||
    m1 >= 60 ||
    m2 < 0 ||
    m2 >= 60
  ) {
    return "❌ الأوقات غير صحيحة أو خارج نطاق اليوم (00:00 - 23:59)";
  }

  // تحويل الوقتين إلى دقائق منذ منتصف الليل
  const total1 = h1 * 60 + m1;
  const total2 = h2 * 60 + m2;

  // طرح الوقت الثاني من الأول
  const diff = total1 - total2;

  // التأكد أن الوقت الأول فعلاً بعد الثاني

  // تحويل الفرق إلى ساعات ودقائق
  const hours = Math.floor(diff / 60);
  const minutes = diff % 60;

  if (hours === 0 && minutes === 0) return "0 د";
  if (hours === 0) return `${minutes} د`;
  if (minutes === 0) return `${hours} س`;
  return `${hours} س ${minutes} د`;
}

const now = new Date();
const time = now.toLocaleTimeString();
console.log(time);
const TableActive = ({ sidebarcollapsed }) => {
  const [filterOpen, setFilterOpen] = useState(false);
  const [selectedSessionTime, setSelectedSessionTime] = useState(null);

  return (
    <div className="grid grid-cols-1  gap-6">
      <div className="xl:col-span-2">
        <div className="space-y-6 ">
          {/* recent order */}
          <div className="bg-base-100 backdrop-blure-xl rounded-2xl border border-base-300 p-6 pt-0 overflow-hidden ">
            <HeaderOfTable
              title={"الفواتير النشطة"}
              filterOpen={filterOpen}
              setFilterOpen={setFilterOpen}
              badge={
                <>
                  <Badge
                    title={"10 فاتورة"}
                    className={"badge-outline  badge-success"}
                  />

                  <Badge
                    title={"30 طفل"}
                    className={"badge-outline  badge-success"}
                  />
                </>
              }
            >
              <FilterSection filterOpen={filterOpen}>
                <div className="flex items-center space-x-6 flex-wrap">
                  <Lable title={"بحث"} className=" w-50 ">
                    <Input
                      id="search"
                      type="text"
                      placeholder="ابحث برقم الفاتورة او رقم الجوال"
                      className={`text-xs`}
                    />
                  </Lable>
                  <Lable title={"المدة"} className=" w-50 ">
                    <Select defaultValue="الكل">
                      <option>الكل</option>
                      <option>ساعة</option>
                      <option>ساعتين</option>
                      <option>مفتوح</option>
                    </Select>
                  </Lable>
                </div>
              </FilterSection>
            </HeaderOfTable>
            {/* Table */}
            <div className="overflow-y-auto">
              <Table>
                <thead>
                  <TrHead>
                    <Th className="rounded-r-lg">رقم الفاتورة</Th>
                    <Th>عدد الاطفال</Th>
                    <Th>رقم الجوال</Th>
                    <Th>مدة الجلسة</Th>
                    <Th>وقت الدخول</Th>
                    <Th>وقت الخروج</Th>
                    <Th>الوقت المتبقي</Th>
                    <Th className="rounded-l-lg">الاجراءات</Th>
                  </TrHead>
                </thead>
                <tbody className="text-center ">
                  {bills.map((bill) => (
                    <TrBody key={bill.id} className="">
                      <Td className="rounded-r-lg">
                        <span className="text-md font-semibold text-primary">
                          # {bill.billNumber}
                        </span>
                      </Td>
                      <Td>
                        <Badge
                          title={`${bill.childrenNumber} طفل`}
                          className="bg-success/20 text-success badge-sm font-semibold  border-0"
                        ></Badge>
                      </Td>
                      <Td>
                        <span className="text-sm font-semibold text-base-content flex items-center justify-center gap-1">
                          <Badge
                            title={"5"}
                            className=" bg-primary/90 border-0 text-primary-content badge-md h-6 w-auto  text-xs"
                          ></Badge>

                          {bill.phoneNumber}
                        </span>
                      </Td>
                      <Td className=" flex flex-col items-center justify-center gap-1">
                        <Badge
                          title={
                            sessionTime.find((s) => s.id === bill.sessionTimeid)
                              .name
                          }
                          style={{
                            backgroundColor: sessionTime.find(
                              (s) => s.id === bill.sessionTimeid
                            ).color,
                          }}
                          className={` opacity-80  
                          } badge-sm font-semibold border-0`}
                        ></Badge>

                        {/* for insert extention badg */}
                        {bill.extends.map((ext) => (
                          <Badge
                            title={
                              sessionTime.find(
                                (s) => s.id === ext.extendsessionTimeId
                              ).sessionTime === null
                                ? "مفتوح"
                                : sessionTime.find(
                                    (s) => s.id === ext.extendsessionTimeId
                                  ).name
                            }
                            key={ext.id}
                            style={{
                              backgroundColor: sessionTime.find(
                                (s) => s.id === ext.extendsessionTimeId
                              ).color,
                            }}
                            className={` opacity-80 badge-sm font-semibold  border-0`}
                          ></Badge>
                        ))}
                      </Td>
                      <Td>
                        <span className="text-sm font-semibold text-base-content">
                          {bill.enterTime} م
                        </span>
                      </Td>
                      <Td>
                        <span className="text-sm font-semibold text-base-content">
                          {bill.exitTime} م
                        </span>
                      </Td>
                      <Td>
                        <span className="text-sm font-semibold text-base-content flex items-center justify-center gap-1 ">
                          <Hourglass className="w-4 h-4 text-error align-middle" />
                          {diffInArabic(bill.exitTime, time)}
                        </span>
                      </Td>
                      <Td className="rounded-l-lg ">
                        <span className="text-sm font-semibold text-base-content flex  justify-center">
                          <Eye className="w-5 h-5 text-primary " />
                        </span>
                      </Td>
                    </TrBody>
                  ))}
                </tbody>
              </Table>
              {/* modal add invoice */}
              <Modal id={"modal_add_invoice"} title={"اضافة فاتورة جديدة"}>
                <div className="bg-base-100 grid grid-cols-1 sm:grid-cols-2 gap-3  w-full">
                  <Lable
                    title={"رقم الفاتورة"}
                    className=" col-span-2 sm:col-span-1"
                  >
                    <Input type="text" placeholder="ادخل رقم الفاتورة" />
                  </Lable>
                  <Lable
                    title={"عدد الاطفال"}
                    className=" col-span-2 sm:col-span-1"
                  >
                    <Input type="number" placeholder="ادخل عدد الاطفال" />
                  </Lable>

                  <Lable title={"رقم الجوال"} className=" col-span-2">
                    <Input type="text" placeholder="ادخل رقم الجوال" />
                  </Lable>
                  <Lable title={"اختر مدة الجلسة"} className={"col-span-2"}>
                    <div className=" w-full grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-4 ">
                      {/* session time cards */}
                      {sessionTime.map((session) => (
                        <SessionCard
                          key={session.id}
                          onClick={() => setSelectedSessionTime(session.id)}
                          boxShadowActive={selectedSessionTime === session.id}
                          session={session}
                        />
                      ))}
                    </div>
                  </Lable>
                </div>
                <div className="flex items-center justify-center mt-10">
                  <MyButton
                    title={"إضافة"}
                    className={
                      " w-full sm:w-1/4  bg-primary shadow-none border border-primary text-primary-content hover:bg-transparent hover:text-primary"
                    }
                  />
                </div>
              </Modal>
              {/*== modal add invoice ==*/}
            </div>
          </div>
          {/* button for open modal add invoice */}
          <FloatButton
            onClick={() => window.modal_add_invoice.showModal()}
            className={sidebarcollapsed ? "right-23" : "right-75"}
          />
          {/*== button for open modal add invoice ==*/}
        </div>
      </div>
    </div>
  );
};

export default TableActive;
